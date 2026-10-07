<#
.SYNOPSIS
    Simple concurrency load test for the CRM read surface (pipeline, leads,
    deals and the crm-summary dashboard card). No external tools required.

.DESCRIPTION
    Logs in once as the admin, then fans out background jobs. Each worker
    repeatedly lists the pipeline stages, flips between open deals and leads
    (heaviest read paths of the CRM slice) plus the dashboard crm-summary tile.
    Prints aggregate RPS and latency-per-request stats. Pairs with
    scripts/crm-pilot.md for observing HPA scaling.

.EXAMPLE
    ./scripts/load-test-crm.ps1 -Concurrency 12 -RequestsPerWorker 50
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$AdminEmail = "admin@nexi-corp.com",
    [string]$AdminPassword = "password",
    [int]$Concurrency = 10,
    [int]$RequestsPerWorker = 50
)

$ErrorActionPreference = "Stop"

Write-Host "== CRM load test ==" -ForegroundColor Cyan
Write-Host "Target    : $BaseUrl"
Write-Host "Concurrent: $Concurrency workers x $RequestsPerWorker requests"
Write-Host "Flow      : GET /api/v1/crm-stages, /api/v1/deals?status=open, /api/v1/leads, /api/v1/dashboard/crm-summary (admin token)"
Write-Host ""

# ---- 1. Admin login (throws early if the server / OAuth client is down) ---------
try {
    $login = Invoke-RestMethod -Uri "$BaseUrl/api/v1/login" -Method Post -ContentType "application/json" `
        -Body (@{ email = $AdminEmail; password = $AdminPassword } | ConvertTo-Json)
} catch {
    Write-Host "Admin login failed. Is the server up? Check Email/Password and PASSPORT_* env." -ForegroundColor Red
    exit 1
}
if (-not $login.access_token) {
    Write-Host "Admin login failed (no access_token). Check PASSPORT_PASSWORD_CLIENT_ID/SECRET." -ForegroundColor Red
    exit 1
}
$token = $login.access_token

# ---- 2. Company of the seeded admin for the dashboard card ------------------------
$headers = @{ Authorization = "Bearer $token"; Accept = "application/json" }
$companyId = 1
try {
    $companies = Invoke-RestMethod -Uri "$BaseUrl/api/v1/companies" -Headers $headers
    $companyId = [int]$companies.data[0].id
} catch {}

# ---- 3. Punchy baseline: how long does one Crm tile take? --------------------------
$sw = [System.Diagnostics.Stopwatch]::StartNew()
Invoke-WebRequest -Uri "$BaseUrl/api/v1/dashboard/crm-summary?company_id=$companyId" -Headers $headers -UseBasicParsing | Out-Null
$sw.Stop()
Write-Host "Single GET /api/v1/dashboard/crm-summary: $([math]::Round($sw.ElapsedMilliseconds, 1)) ms"
Write-Host ""

# ---- 4. Concurrent workers: stage list + open deals + leads + crm tile --------------
$worker = {
    param($Url, $Hdrs, $Requests, $CompanyId)
    $failures = 0
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    for ($i = 0; $i -lt $Requests; $i++) {
        $paths = @(
            "/api/v1/crm-stages?per_page=50",
            "/api/v1/deals?status=open&per_page=50",
            "/api/v1/leads?status=qualified&per_page=50",
            "/api/v1/dashboard/crm-summary?company_id=$CompanyId"
        )
        $path = $paths[$i % $paths.Count]
        try {
            Invoke-WebRequest -Uri "$Url$path" -Headers $Hdrs -UseBasicParsing | Out-Null
        } catch {
            $failures++
        }
    }
    $sw.Stop()

    [pscustomobject]@{
        ElapsedMs = $sw.ElapsedMilliseconds
        Failures  = $failures
        Requests  = $Requests
    }
}

$jobs = 1..$Concurrency | ForEach-Object {
    Start-Job -ScriptBlock $worker -ArgumentList $BaseUrl, $headers, $RequestsPerWorker, $companyId
}

$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job

$totalRequests = ($results | Measure-Object -Property Requests -Sum).Sum
$totalElapsedMs = ($results | Measure-Object -Property ElapsedMs -Sum).Sum
$failures = ($results | Measure-Object -Property Failures -Sum).Sum
$avgMsPerRequest = if ($totalRequests -gt 0) { $totalElapsedMs / $totalRequests } else { 0 }

Write-Host ""
Write-Host "Finished $totalRequests requests across $Concurrency workers in $([math]::Round($totalElapsedMs / 1000, 2))s (wall)"
Write-Host ("Average latency per request : {0:N1} ms" -f $avgMsPerRequest)
Write-Host ("Effective throughput         : {0:N1} req/s" -f ($totalRequests / ($totalElapsedMs / 1000)))
Write-Host "Failures                     : $failures"

if ($failures -gt 0) {
    Write-Host "LOAD TEST FAILED ($failures errors)" -ForegroundColor Red
    exit 1
}
Write-Host "LOAD TEST PASSED" -ForegroundColor Green
exit 0