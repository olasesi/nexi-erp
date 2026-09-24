<#
.SYNOPSIS
    End-to-end smoke test for the Auth feature (API only, no frontend).

.DESCRIPTION
    Exercises login (success + failure), staff registration, the customer
    portal (customer register, me, contact-scoped invoices), token logout,
    staff/customer separation (403s), unauthenticated access (401s) and the
    auth counters exposed on /api/metrics. Prints one PASS/FAIL per assertion
    and exits non-zero if any assertion fails.

    Requires the local API to be running (php artisan serve) with a seeded
    database, the Passport password client configured, and at least one active
    customer/both contact linked to the admin company (see scripts/auth-pilot.md).

    Rows created during the run (the staff user, the customer user and the
    customer contact, all keyed by unique emails) are removed afterwards.

.EXAMPLE
    ./scripts/test-auth.ps1
    ./scripts/test-auth.ps1 -BaseUrl https://api.nexi-erp.com -Email admin@x.com -Password pw
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Email = "admin@nexi-corp.com",
    [string]$Password = "password",
    [int]$CompanyId = 1
)

$ErrorActionPreference = "Stop"

$ApiDir = Join-Path $PSScriptRoot "..\api"

$RunId = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$StaffEmail = "ops+$RunId@nexi-corp.test"
$CustomerEmail = "customer+$RunId@nexi-corp.test"

$script:Pass = 0
$script:Fail = 0

function Assert {
    param([bool]$Condition, [string]$Name)
    if ($Condition) {
        $script:Pass++
        Write-Host "  [PASS] $Name" -ForegroundColor Green
    } else {
        $script:Fail++
        Write-Host "  [FAIL] $Name" -ForegroundColor Red
    }
}

function Invoke-Api {
    param(
        [string]$Method = "GET",
        [string]$Path,
        [string]$Token = "",
        [object]$Body = $null
    )
    $headers = @{ "Accept" = "application/json" }
    if ($Token) {
        $headers["Authorization"] = "Bearer $Token"
    }

    $params = @{
        Uri            = "$BaseUrl$Path"
        Method         = $Method
        Headers        = $headers
        UseBasicParsing = $true
    }
    if ($null -ne $Body) {
        $params.ContentType = "application/json"
        $params.Body = $Body | ConvertTo-Json
    }

    try {
        $res = Invoke-WebRequest @params
        $parsed = $null
        try { $parsed = $res.Content | ConvertFrom-Json } catch {}
        return [pscustomobject]@{
            Status      = [int]$res.StatusCode
            Body        = $parsed
            Raw         = $res.Content
            ContentType = "$($res.Headers['Content-Type'])"
        }
    } catch {
        $status = 0
        $content = ""
        if ($_.Exception.Response) {
            $status = [int]$_.Exception.Response.StatusCode
            try {
                $stream = $_.Exception.Response.GetResponseStream()
                if ($stream) {
                    $reader = New-Object System.IO.StreamReader($stream)
                    $content = $reader.ReadToEnd()
                }
            } catch {}
        }
        $parsed = $null
        try { $parsed = $content | ConvertFrom-Json } catch {}
        return [pscustomobject]@{
            Status      = $status
            Body        = $parsed
            Raw         = $content
            ContentType = ""
        }
    }
}

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

function New-CustomerContact {
    param([string]$CustomerEmail)
    $php = "`$c = \App\Models\Contact::firstOrCreate(" +
        "['email' => '$CustomerEmail'], " +
        "['company_id' => $CompanyId, 'type' => 'customer', 'first_name' => 'Smoke', 'last_name' => 'Customer', 'is_active' => true]);" +
        "echo `$c->id;"
    $id = Invoke-Tinker -PhpCode $php
    try { return [int]$id } catch { return 0 }
}

function Remove-TestRows {
    $php = "foreach ([\App\Models\User::where('email','$StaffEmail')->get(), " +
        "\App\Models\User::where('email','$CustomerEmail')->get()] as `$users) { " +
        "foreach (`$users as `$u) { `$u->tokens()->delete(); `$u->delete(); } }" +
        "\App\Models\Contact::where('email','$CustomerEmail')->forceDelete();" +
        "echo 'cleaned';"
    Invoke-Tinker -PhpCode $php | Out-Null
}

Write-Host "== Auth feature smoke test ==" -ForegroundColor Cyan
Write-Host "Base URL : $BaseUrl"
Write-Host "Company  : $CompanyId"
Write-Host "Run ID   : $RunId"
Write-Host ""

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[FATAL] php not found on PATH" -ForegroundColor Red
    exit 1
}

# Seed a customer contact for the portal tests -----------------------------------
$contactId = New-CustomerContact -CustomerEmail $CustomerEmail
Assert ($contactId -gt 0) "ensured a customer contact exists for the portal tests (id=$contactId)"
if ($contactId -le 0) {
    Write-Host "[FATAL] could not create the customer contact via tinker" -ForegroundColor Red
    exit 1
}

# 1. Health + unauthenticated ----------------------------------------------------
$health = Invoke-Api -Path "/api/health"
Assert ($health.Status -eq 200 -and $health.Body.status -eq "healthy") "GET /api/health returns healthy"

$noAuth = Invoke-Api -Path "/api/v1/user"
Assert ($noAuth.Status -eq 401) "GET /api/v1/user without token returns 401"

$noAuthLogin = Invoke-Api -Path "/api/v1/customer/invoices"
Assert ($noAuthLogin.Status -eq 401) "GET /api/v1/customer/invoices without token returns 401"

# 2. Staff login + identity + logout ---------------------------------------------
$wrong = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = "wrong-password" }
Assert ($wrong.Status -eq 422) "POST /api/v1/login with wrong password returns 422"

$login = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = $Password }
Assert ($login.Status -eq 200 -and -not [string]::IsNullOrEmpty($login.Body.access_token)) "POST /api/v1/login issues an access token"
Assert ($login.Body.token_type -eq "Bearer") "token_type is Bearer"
Assert ($login.Body.user.email -eq $Email) "login response includes the staff user"

if ($login.Status -ne 200) {
    Write-Host ""
    Write-Host "Login failed. Have you run 'php artisan migrate:fresh --seed' and configured" -ForegroundColor Yellow
    Write-Host "PASSPORT_PASSWORD_CLIENT_ID / PASSPORT_PASSWORD_CLIENT_SECRET (see scripts/auth-pilot.md)?" -ForegroundColor Yellow
    exit 1
}
$token = $login.Body.access_token

$me = Invoke-Api -Path "/api/v1/user" -Token $token
Assert ($me.Status -eq 200 -and $me.Body.email -eq $Email) "GET /api/v1/user returns the authenticated staff user"

# 3. Staff registration -----------------------------------------------------------
$reg = Invoke-Api -Method POST -Path "/api/v1/register" -Body @{
    name                  = "Ops Smoke $RunId"
    email                 = $StaffEmail
    password              = "password123"
    password_confirmation = "password123"
}
Assert ($reg.Status -eq 200 -and $reg.Body.user.email -eq $StaffEmail) "POST /api/v1/register creates a staff user"
$staffToken = $reg.Body.access_token

$dup = Invoke-Api -Method POST -Path "/api/v1/register" -Body @{
    name                  = "Ops Smoke $RunId"
    email                 = $StaffEmail
    password              = "password123"
    password_confirmation = "password123"
}
Assert ($dup.Status -eq 422) "POST /api/v1/register with an existing email returns 422"

# 4. Logout revokes the token ------------------------------------------------------
$logout = Invoke-Api -Method POST -Path "/api/v1/logout" -Token $staffToken
Assert ($logout.Status -eq 200 -and $logout.Body.message -eq "Logged out successfully") "POST /api/v1/logout succeeds"
$afterLogout = Invoke-Api -Path "/api/v1/user" -Token $staffToken
Assert ($afterLogout.Status -eq 401) "revoked token is rejected (401)"

# 5. Customer portal ----------------------------------------------------------------
$portalReg = Invoke-Api -Method POST -Path "/api/v1/customer/register" -Body @{
    name                  = "Smoke Customer $RunId"
    email                 = $CustomerEmail
    password              = "password123"
    password_confirmation = "password123"
}
Assert ($portalReg.Status -eq 201 -and -not [string]::IsNullOrEmpty($portalReg.Body.access_token)) "POST /api/v1/customer/register returns 201 + token"
Assert ($portalReg.Body.user.contact_id -eq $contactId) "customer user is linked to the contact"

$customerToken = $portalReg.Body.access_token

$customerMe = Invoke-Api -Path "/api/v1/customer/me" -Token $customerToken
Assert ($customerMe.Status -eq 200 -and $customerMe.Body.contact.id -eq $contactId) "GET /api/v1/customer/me exposes the linked contact"
Assert ($customerMe.Body.company_id -eq $CompanyId) "customer me reports the owning company"

$myInvoices = Invoke-Api -Path "/api/v1/customer/invoices" -Token $customerToken
Assert ($myInvoices.Status -eq 200) "GET /api/v1/customer/invoices returns 200"

$myQuotes = Invoke-Api -Path "/api/v1/customer/quotations" -Token $customerToken
Assert ($myQuotes.Status -eq 200) "GET /api/v1/customer/quotations returns 200"

$myOrders = Invoke-Api -Path "/api/v1/customer/sales-orders" -Token $customerToken
Assert ($myOrders.Status -eq 200) "GET /api/v1/customer/sales-orders returns 200"

$myPayments = Invoke-Api -Path "/api/v1/customer/payments" -Token $customerToken
Assert ($myPayments.Status -eq 200) "GET /api/v1/customer/payments returns 200"

# 6. Separation of concerns --------------------------------------------------------
$staffBlocked = Invoke-Api -Path "/api/v1/customer/me" -Token $token
Assert ($staffBlocked.Status -eq 403) "staff token is blocked from the customer portal (403)"

$customerBlocked = Invoke-Api -Path "/api/v1/contacts" -Token $customerToken
Assert ($customerBlocked.Status -eq 403) "customer token is blocked from the staff API (403)"

$customerBlockedSettings = Invoke-Api -Path "/api/v1/settings" -Token $customerToken
Assert ($customerBlockedSettings.Status -eq 403) "customer token is blocked from settings (403)"

# 7. Auth metrics ------------------------------------------------------------------
$metrics = Invoke-Api -Path "/api/metrics"
Assert ($metrics.Status -eq 200 -and $metrics.ContentType -match "text/plain") "GET /api/metrics returns Prometheus text"
Assert ($metrics.Raw -match "nexi_erp_auth_logins_total [1-9]") "/api/metrics counts successful logins"
Assert ($metrics.Raw -match "nexi_erp_auth_login_failures_total [1-9]") "/api/metrics counts failed logins"
Assert ($metrics.Raw -match "nexi_erp_auth_registrations_total [1-9]") "/api/metrics counts registrations"

# 8. Cleanup -----------------------------------------------------------------------
Remove-TestRows
Write-Host ""
Write-Host "Cleanup: removed the smoke-test staff user, customer user and contact" -ForegroundColor Gray

Write-Host ""
Write-Host "== Result: $script:Pass passed, $script:Fail failed ==" -ForegroundColor Cyan
if ($script:Fail -gt 0) {
    Write-Host "SMOKE TEST FAILED" -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE TEST PASSED" -ForegroundColor Green
exit 0