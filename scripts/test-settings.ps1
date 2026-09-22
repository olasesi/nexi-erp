<#
.SYNOPSIS
    End-to-end smoke test for the Settings feature (API only, no frontend).

.DESCRIPTION
    Exercises login, health, the settings CRUD/update endpoints, validation
    (422s), encrypted-at-rest secrets, company scoping, cache clearing, test
    email, 401 handling and the /api/metrics endpoint. Prints one PASS/FAIL
    per assertion and exits non-zero if any assertion fails.

    Requires the local API to be running (php artisan serve) with a seeded
    database and the Passport password client configured (see scripts/settings-pilot.md).

    Additive rows written during the run (brand titleText, themeMode,
    email mailDriver, mailPassword, plus a company-scoped brand titleText)
    are removed afterwards so defaults are restored.

.EXAMPLE
    ./scripts/test-settings.ps1
    ./scripts/test-settings.ps1 -BaseUrl https://api.nexi-erp.com -Email admin@x.com -Password pw
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
    $headers = @{}
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

function Reset-SettingsRows {
    $php = "`$rows = \App\Models\Setting::where('namespace','app')" +
        "->whereIn('group',['brand','email'])" +
        "->whereIn('key',['titleText','themeMode','mailDriver','mailPassword'])" +
        "->get();" +
        "foreach (`$rows as `$row) { `$row->delete(); }" +
        "echo 'cleaned';"
    Invoke-Tinker -PhpCode $php | Out-Null
}

Write-Host "== Settings feature smoke test ==" -ForegroundColor Cyan
Write-Host "Base URL : $BaseUrl"
Write-Host "Company  : $CompanyId"
Write-Host ""

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[FATAL] php not found on PATH" -ForegroundColor Red
    exit 1
}

# 1. Health ----------------------------------------------------------------
$health = Invoke-Api -Path "/api/health"
Assert ($health.Status -eq 200 -and $health.Body.status -eq "healthy") "GET /api/health returns healthy"

# 2. Auth ----------------------------------------------------------------
$noAuth = Invoke-Api -Path "/api/v1/settings"
Assert ($noAuth.Status -eq 401) "GET /api/v1/settings without token returns 401"

$login = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = $Password }
Assert ($login.Status -eq 200 -and -not [string]::IsNullOrEmpty($login.Body.access_token)) "POST /api/v1/login issues an access token"

if ($login.Status -ne 200) {
    Write-Host ""
    Write-Host "Login failed. Have you run 'php artisan migrate:fresh --seed' and configured" -ForegroundColor Yellow
    Write-Host "PASSPORT_PASSWORD_CLIENT_ID / PASSPORT_PASSWORD_CLIENT_SECRET (see scripts/settings-pilot.md)?" -ForegroundColor Yellow
    exit 1
}
$token = $login.Body.access_token

# 3. Index + defaults -------------------------------------------------------
$settings = Invoke-Api -Path "/api/v1/settings" -Token $token
Assert ($settings.Status -eq 200) "GET /api/v1/settings returns 200"
Assert ($settings.Body.data.brand.titleText -eq "Nexi ERP") "brand.titleText defaults to 'Nexi ERP'"
Assert ($settings.Body.data.system.defaultLanguage -eq "en") "system.defaultLanguage defaults to 'en'"
Assert (@($settings.Body.meta.available_languages.PSObject.Properties).Count -eq 15) "meta lists exactly 15 languages"
Assert (@($settings.Body.meta.themes).Count -eq 3) "meta lists 3 themes"

# 4. Group read -------------------------------------------------------------
$brand = Invoke-Api -Path "/api/v1/settings/brand" -Token $token
Assert ($brand.Status -eq 200 -and $brand.Body.data.themeMode -eq "light") "GET /api/v1/settings/brand returns light theme default"

# 5. Update + persistence ---------------------------------------------------
$update = Invoke-Api -Method PUT -Path "/api/v1/settings/brand" -Token $token -Body @{ titleText = "Smoke Test"; themeMode = "twilight" }
Assert ($update.Status -eq 200 -and $update.Body.data.titleText -eq "Smoke Test") "PUT brand persists titleText='Smoke Test'"
Assert ($update.Body.data.themeMode -eq "twilight") "PUT brand persists themeMode='twilight'"

$reload = Invoke-Api -Path "/api/v1/settings/brand" -Token $token
Assert ($reload.Body.data.titleText -eq "Smoke Test") "value persists across requests"

# 6. Validation -------------------------------------------------------------
$invalid = Invoke-Api -Method PUT -Path "/api/v1/settings/brand" -Token $token -Body @{ titleText = "Smoke Test"; themeMode = "neon" }
Assert ($invalid.Status -eq 422) "PUT brand with invalid themeMode returns 422"

$unknownGroup = Invoke-Api -Method PUT -Path "/api/v1/settings/nope" -Token $token -Body @{ x = "y" }
Assert ($unknownGroup.Status -eq 404) "PUT unknown settings group returns 404"

$unknownKey = Invoke-Api -Method PUT -Path "/api/v1/settings/brand" -Token $token -Body @{ notARealKey = "x" }
Assert ($unknownKey.Status -eq 422) "PUT brand with unknown key returns 422"

# 7. Company scoping --------------------------------------------------------
$companyPut = Invoke-Api -Method PUT -Path "/api/v1/settings/brand?company_id=$CompanyId" -Token $token -Body @{ titleText = "Acme" }
Assert ($companyPut.Status -eq 200 -and $companyPut.Body.data.titleText -eq "Acme") "company-scoped PUT brand persists 'Acme'"

$companyGet = Invoke-Api -Path "/api/v1/settings/brand?company_id=$CompanyId" -Token $token
Assert ($companyGet.Body.data.titleText -eq "Acme") "company-scoped GET returns company value"

$globalGet = Invoke-Api -Path "/api/v1/settings/brand" -Token $token
Assert ($globalGet.Body.data.titleText -eq "Smoke Test") "global GET unaffected by company row"

# 8. Secrets encrypted at rest ---------------------------------------------
$secret = "s3cret!"
$emailPut = Invoke-Api -Method PUT -Path "/api/v1/settings/email" -Token $token -Body @{ mailDriver = "smtp"; mailPassword = $secret }
Assert ($emailPut.Status -eq 200) "PUT email accepts a mail password"

$emailGet = Invoke-Api -Path "/api/v1/settings/email" -Token $token
Assert ($emailGet.Body.data.mailPassword -eq $secret) "GET email returns the decrypted mail password"

$stored = Invoke-Tinker -PhpCode "`$row = \App\Models\Setting::where('namespace','app')->whereNull('company_id')->where('group','email')->where('key','mailPassword')->first(); echo `$row ? `$row->value : 'NOT_FOUND';"
Assert (-not [string]::IsNullOrEmpty($stored) -and $stored -ne "NOT_FOUND" -and $stored -ne $secret) "stored mail password differs from plaintext (encrypted at rest)"

# 9. Clear cache ------------------------------------------------------------
$clear = Invoke-Api -Method POST -Path "/api/v1/settings/cache/clear" -Token $token
Assert ($clear.Status -eq 200 -and $clear.Body.message -eq "Cache cleared successfully.") "POST cache/clear returns success"
Assert ($clear.Body.cache_size -match "^\d+\.\d{2}$") "cache_size is reported as a formatted number"

# 10. Test email ------------------------------------------------------------
$mail = Invoke-Api -Method POST -Path "/api/v1/settings/email/test" -Token $token -Body @{ email = "ops@nexi-corp.test" }
Assert ($mail.Status -eq 200 -and $mail.Body.message -eq "Test email sent successfully.") "POST email/test sends a test email"

# 11. Metrics ---------------------------------------------------------------
$metrics = Invoke-Api -Path "/api/metrics"
Assert ($metrics.Status -eq 200 -and $metrics.ContentType -match "text/plain") "GET /api/metrics returns Prometheus text"
Assert ($metrics.Raw -match "nexi_erp_up 1") "/api/metrics includes nexi_erp_up"
Assert ($metrics.Raw -match "nexi_erp_http_requests_total ") "/api/metrics includes the request counter"

# 12. Cleanup ---------------------------------------------------------------
Reset-SettingsRows
Write-Host ""
Write-Host "Cleanup: restored default settings (removed rows written by this run)" -ForegroundColor Gray

Write-Host ""
Write-Host "== Result: $script:Pass passed, $script:Fail failed ==" -ForegroundColor Cyan
if ($script:Fail -gt 0) {
    Write-Host "SMOKE TEST FAILED" -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE TEST PASSED" -ForegroundColor Green
exit 0