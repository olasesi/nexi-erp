<#
.SYNOPSIS
    Simple concurrency load test against the Settings API (no external tools).

.DESCRIPTION
    Logs in once, then fans out a number of background jobs that hit a
    settings endpoint concurrently. Prints aggregate RPS and latency-per-job
    stats. Pairs with scripts/settings-pilot.md for observing HPA scaling.

    For heavier load prefer a dedicated tool (hey, k6, ab) - see the runbook.

.EXAMPLE
    ./scripts/load-test.ps1 -Concurrency 20 -RequestsPerWorker 100
    ./scripts/load-test.ps1 -Path "/api/v1/settings/brand?company_id=1"
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Email = "admin@nexi-corp.com",
    [string]$Password = "password",
    [int]$Concurrency = 10,
    [int]$RequestsPerWorker = 50,
    [string]$Path = "/api/v1/settings"
)

$ErrorActionPreference = "Stop"

Write-Host "== Settings load test ==" -ForegroundColor Cyan
Write-Host "Target    : $BaseUrl$Path"
Write-Host "Concurrent: $Concurrency workers x $RequestsPerWorker requests"
Write-Host ""

$login = Invoke-RestMethod -Uri "$BaseUrl/api/v1/login" -Method Post -ContentType "application/json" `
    -Body (@{ email = $Email; password = $Password } | ConvertTo-Json)

if (-not $login.access_token) {
    Write-Host "Login failed. Check Email/Password and API availability." -ForegroundColor Red
    exit 1
}
$token = $login.access_token

$worker = {
    param($Url, $AuthToken, $Path, $Requests)
    $headers = @{ Authorization = "Bearer $AuthToken" }
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    for ($i = 0; $i -lt $Requests; $i++) {
        try {
            Invoke-WebRequest -Uri "$Url$Path" -Headers $headers -UseBasicParsing | Out-Null
        } catch {
            # count failures separately
            $script:ServerFailures++
        }
    }
    $sw.Stop()
    [pscustomobject]@{
        Requests    = $Requests
        ElapsedMs   = $sw.ElapsedMilliseconds
    }
}

$script:ServerFailures = 0
$wall = [System.Diagnostics.Stopwatch]::StartNew()
$jobs = @()
for ($i = 0; $i -lt $Concurrency; $i++) {
    $jobs += Start-Job -ScriptBlock $worker -ArgumentList $BaseUrl, $token, $Path, $RequestsPerWorker
}
$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job
$wall.Stop()

$totalRequests = ($results | Measure-Object -Property Requests -Sum).Sum
$successful = $totalRequests - $script:ServerFailures
$seconds = [math]::Max($wall.Elapsed.TotalSeconds, 0.001)

Write-Host "Wall time   : $([math]::Round($seconds, 2)) s"
Write-Host "Requests    : $totalRequests total, $successful ok, $script:ServerFailures failed"
Write-Host "Throughput  : $([math]::Round($successful / $seconds, 1)) req/s"
$avgMs = ($results | Measure-Object -Property ElapsedMs -Average).Average / [math]::Max($RequestsPerWorker, 1)
Write-Host "Avg latency (per request, per worker): $([math]::Round($avgMs, 1)) ms"
Write-Host ""

if ($script:ServerFailures -gt 0) {
    Write-Host "LOAD TEST FAILED ($script:ServerFailures failures)" -ForegroundColor Red
    exit 1
}
Write-Host "LOAD TEST PASSED" -ForegroundColor Green
exit 0