<#
.SYNOPSIS
    Simple concurrency load test for the Notifications read surface (in-app
    inbox list + unread-count badge). No external tools required.

.DESCRIPTION
    Logs in once as the admin, then fans out background jobs. Each worker
    repeatedly lists the notifications inbox and hits the unread-count badge
    endpoint - the heaviest read paths of the notifications slice.
    Prints aggregate RPS and latency-per-request stats. Pairs with
    scripts/notifications-pilot.md for observing HPA scaling.

.EXAMPLE
    ./scripts/load-test-notifications.ps1 -Concurrency 12 -RequestsPerWorker 50
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

Write-Host "== Notifications load test ==" -ForegroundColor Cyan
Write-Host "Target    : $BaseUrl"
Write-Host "Concurrent: $Concurrency workers x $RequestsPerWorker requests"
Write-Host "Flow      : GET /api/v1/notifications and GET /api/v1/notifications/unread-count (admin token)"
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

# ---- 2. Punchy baseline: how long does one inbox list take? ----------------------
$headers = @{ Authorization = "Bearer $token"; Accept = "application/json" }
$sw = [System.Diagnostics.Stopwatch]::StartNew()
Invoke-WebRequest -Uri "$BaseUrl/api/v1/notifications" -Headers $headers -UseBasicParsing | Out-Null
$sw.Stop()
Write-Host "Single GET /api/v1/notifications: $([math]::Round($sw.ElapsedMilliseconds, 1)) ms"
Write-Host ""

# ---- 3. Concurrent workers: list inbox + unread badge ------------------------------
$worker = {
    param($Url, $Hdrs, $Requests)
    $failures = 0
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    for ($i = 0; $i -lt $Requests; $i++) {
        try {
            Invoke-WebRequest -Uri "$Url/api/v1/notifications" -Headers $Hdrs -UseBasicParsing | Out-Null
            Invoke-WebRequest -Uri "$Url/api/v1/notifications/unread-count" -Headers $Hdrs -UseBasicParsing | Out-Null
        } catch {
            $failures++
        }
    }
    $sw.Stop()
    [pscustomobject]@{
        ElapsedMs = $sw.ElapsedMilliseconds
        Failures  = $failures
    }
}

$wall = [System.Diagnostics.Stopwatch]::StartNew()
$jobs = @()
for ($i = 0; $i -lt $Concurrency; $i++) {
    $jobs += Start-Job -ScriptBlock $worker -ArgumentList $BaseUrl, $headers, $RequestsPerWorker
}
$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job
$wall.Stop()

$totalRequests = $Concurrency * $RequestsPerWorker * 2   # inbox + unread-count
$totalFailures = ($results | Measure-Object -Property Failures -Sum).Sum
$successful = $totalRequests - $totalFailures
$seconds = [math]::Max($wall.Elapsed.TotalSeconds, 0.001)

Write-Host "Wall time   : $([math]::Round($seconds, 2)) s"
Write-Host "Requests    : $totalRequests total (list inbox + unread-count), $successful ok, $totalFailures failed"
Write-Host "Throughput  : $([math]::Round($successful / $seconds, 1)) req/s"
$avgPairMs = ($results | Measure-Object -Property ElapsedMs -Average).Average / [math]::Max($RequestsPerWorker, 1) / 2
Write-Host "Avg latency  (inbox+unread pair): $([math]::Round($avgPairMs * 2, 1)) ms"
Write-Host "Avg latency per request         : $([math]::Round($avgPairMs, 1)) ms"
Write-Host ""

if ($totalFailures -gt 0) {
    Write-Host "LOAD TEST FAILED ($totalFailures failures)" -ForegroundColor Red
    exit 1
}
Write-Host "LOAD TEST PASSED" -ForegroundColor Green
exit 0