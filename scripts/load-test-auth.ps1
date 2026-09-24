<#
.SYNOPSIS
    Simple concurrency load test for the Auth feature (staff login + customer
    portal reads). No external tools required.

.DESCRIPTION
    Ensures a smoke-test customer contact + user exists (created via tinker and
    removed afterwards), then fans out background jobs. Each job logs in as the
    customer (exercising the Passport password grant under concurrency) and
    reads the customer's invoices. Prints aggregate RPS and latency-per-job
    stats. Pairs with scripts/auth-pilot.md for observing HPA scaling.

    Raises HPA CPU pressure through real login work (the password grant is the
    auth slice's most expensive operation) while also exercising the customer
    portal read path.

.EXAMPLE
    ./scripts/load-test-auth.ps1 -Concurrency 20 -RequestsPerWorker 50
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$StaffEmail = "admin@nexi-corp.com",
    [string]$StaffPassword = "password",
    [int]$Concurrency = 10,
    [int]$RequestsPerWorker = 50,
    [int]$CompanyId = 1
)

$ErrorActionPreference = "Stop"

$ApiDir = Join-Path $PSScriptRoot "..\api"
$RunId = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$CustomerEmail = "load+$RunId@nexi-corp.test"
$CustomerPassword = "password123"

Write-Host "== Auth load test ==" -ForegroundColor Cyan
Write-Host "Target    : $BaseUrl"
Write-Host "Concurrent: $Concurrency workers x $RequestsPerWorker requests"
Write-Host "Flow      : customer login (password grant) -> GET /api/v1/customer/invoices"
Write-Host ""

# ---- 1. Sanity: staff login (throws early if OAuth client isn't configured) ----
try {
    $staff = Invoke-RestMethod -Uri "$BaseUrl/api/v1/login" -Method Post -ContentType "application/json" `
        -Body (@{ email = $StaffEmail; password = $StaffPassword } | ConvertTo-Json)
} catch {
    Write-Host "Staff login failed. Is the server up? Check Email/Password and PASSPORT_* env." -ForegroundColor Red
    exit 1
}
if (-not $staff.access_token) {
    Write-Host "Staff login failed (no access_token). Check PASSPORT_PASSWORD_CLIENT_ID/SECRET." -ForegroundColor Red
    exit 1
}

# ---- 2. Provision a load-test customer contact + user (tinker) -------------------
function Invoke-Tinker {
    param([string]$PhpCode)
    Push-Location $ApiDir
    try {
        $out = @(& php artisan tinker --execute="$PhpCode" 2>&1)
        return (($out | Select-Object -Last 1).ToString().Trim())
    } finally {
        Pop-Location
    }
}

$contactPhp = "`$c = \App\Models\Contact::firstOrCreate([" +
    "'email' => '$CustomerEmail'], " +
    "['company_id' => $CompanyId, 'type' => 'customer', 'first_name' => 'Load', 'last_name' => 'Customer', 'is_active' => true]); echo `$c->id;"
$contactId = Invoke-Tinker -PhpCode $contactPhp
try { $contactId = [int]$contactId } catch {}

if ($contactId -le 0) {
    Write-Host "Could not provision the load-test customer contact via tinker." -ForegroundColor Red
    exit 1
}

$provision = Invoke-RestMethod -Uri "$BaseUrl/api/v1/customer/register" -Method Post -ContentType "application/json" `
    -Body (@{
        name                  = "Load Customer $RunId"
        email                 = $CustomerEmail
        password              = $CustomerPassword
        password_confirmation = $CustomerPassword
    } | ConvertTo-Json)

if ($provision.StatusCode -gt 200 -and -not $provision.access_token) {
    Write-Host "Customer provision failed."
    $provision | ConvertTo-Json -Depth 4
    exit 1
}

Write-Host "Customer provisioned: $CustomerEmail (contact id $contactId)"
Write-Host ""

# ---- 3. Punchy baseline: how long does one login take? ---------------------------
$sw = [System.Diagnostics.Stopwatch]::StartNew()
$probe = Invoke-RestMethod -Uri "$BaseUrl/api/v1/login" -Method Post -ContentType "application/json" `
    -Body (@{ email = $CustomerEmail; password = $CustomerPassword } | ConvertTo-Json)
$sw.Stop()
Write-Host "Single customer login: $([math]::Round($sw.ElapsedMilliseconds, 1)) ms"
Write-Host ""

# ---- 4. Concurrent workers: login (password grant) -> invoice read ---------------
$worker = {
    param($Url, $Email, $Password, $Requests)
    $failures = 0
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    for ($i = 0; $i -lt $Requests; $i++) {
        try {
            $login = Invoke-RestMethod -Uri "$Url/api/v1/login" -Method Post -ContentType "application/json" `
                -Body (@{ email = $Email; password = $Password } | ConvertTo-Json)
            if (-not $login.access_token) {
                $failures++
                continue
            }
            $headers = @{ Authorization = "Bearer $($login.access_token)" }
            Invoke-WebRequest -Uri "$Url/api/v1/customer/invoices" -Headers $headers -UseBasicParsing | Out-Null
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
    $jobs += Start-Job -ScriptBlock $worker -ArgumentList $BaseUrl, $CustomerEmail, $CustomerPassword, $RequestsPerWorker
}
$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job
$wall.Stop()

$totalRequests = $Concurrency * $RequestsPerWorker
$totalFailures = ($results | Measure-Object -Property Failures -Sum).Sum
$successful = $totalRequests - $totalFailures
$seconds = [math]::Max($wall.Elapsed.TotalSeconds, 0.001)

Write-Host "Wall time   : $([math]::Round($seconds, 2)) s"
Write-Host "Requests    : $totalRequests total (login+customer read pairs), $successful ok, $totalFailures failed"
Write-Host "Throughput  : $([math]::Round($successful / $seconds, 1)) req/s"
$avgPairMs = ($results | Measure-Object -Property ElapsedMs -Average).Average / [math]::Max($RequestsPerWorker, 1)
Write-Host "Avg latency (login+read pair, per worker): $([math]::Round($avgPairMs, 1)) ms"
Write-Host ""

# ---- 5. Cleanup ---------------------------------------------------------------
$cleanupPhp = "foreach (\App\Models\User::where('email','$CustomerEmail')->get() as `$u) { `$u->tokens()->delete(); `$u->delete(); }" +
    "\App\Models\Contact::where('email','$CustomerEmail')->forceDelete(); echo 'cleaned';"
Invoke-Tinker -PhpCode $cleanupPhp | Out-Null
Write-Host "Cleanup: removed load-test customer user + contact" -ForegroundColor Gray

if ($totalFailures -gt 0) {
    Write-Host "LOAD TEST FAILED ($totalFailures failures)" -ForegroundColor Red
    exit 1
}
Write-Host "LOAD TEST PASSED" -ForegroundColor Green
exit 0