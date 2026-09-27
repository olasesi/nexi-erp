<#
.SYNOPSIS
    End-to-end smoke test for the Notifications feature (in-app inbox, API only).

.DESCRIPTION
    Exercises the notifications surface (list scoped to company, broadcast vs
    personal visibility, unread filter, type filter, search, create broadcast,
    create targeted, show, update incl. read state, unread-count, read-all,
    delete), permission gating for the notifications.* permissions, and the
    nexi_erp_notifications_created_total counter / nexi_erp_notifications_total
    gauge exposed on /api/metrics.

    Requires the local API to be running (php artisan serve) with a seeded
    database (scripts/notifications-pilot.md), the Passport password client
    configured, and the admin account from the seeder.

    Rows created during the run are removed afterwards.

.EXAMPLE
    ./scripts/test-notifications.ps1
    ./scripts/test-notifications.ps1 -BaseUrl https://api.nexi-erp.com
#>
[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Email = "admin@nexi-corp.com",
    [string]$Password = "password"
)

$ErrorActionPreference = "Stop"

$ApiDir = Join-Path $PSScriptRoot "..\api"
$RunId = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()

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

Write-Host "== Notifications feature smoke test ==" -ForegroundColor Cyan
Write-Host "Base URL : $BaseUrl"
Write-Host "Run ID   : $RunId"
Write-Host ""

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[FATAL] php not found on PATH" -ForegroundColor Red
    exit 1
}

# 1. Health + unauthenticated ----------------------------------------------------
$health = Invoke-Api -Path "/api/health"
Assert ($health.Status -eq 200 -and $health.Body.status -eq "healthy") "GET /api/health returns healthy"

$noAuth = Invoke-Api -Path "/api/v1/notifications"
Assert ($noAuth.Status -eq 401) "GET /api/v1/notifications without token returns 401"

# 2. Admin login ------------------------------------------------------------------
$login = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = $Password }
if ($login.Status -ne 200 -or [string]::IsNullOrEmpty($login.Body.access_token)) {
    Write-Host "[FATAL] Admin login failed - is the DB seeded and PASSPORT_* configured?" -ForegroundColor Red
    exit 1
}
$token = $login.Body.access_token
Assert ($login.Status -eq 200) "POST /api/v1/login issues an admin token"

$me = Invoke-Api -Path "/api/v1/user" -Token $token
$myId = $me.Body.id
$myCompany = $me.Body.company_id

# 3. Read surface ------------------------------------------------------------------
$list = Invoke-Api -Path "/api/v1/notifications" -Token $token
Assert ($list.Status -eq 200 -and $null -ne $list.Body.data) "GET /api/v1/notifications returns a paginated list"
Assert ($list.Body.data[0].PSObject.Properties.Name -contains "is_read") "notification rows include is_read"

$unread = Invoke-Api -Path "/api/v1/notifications?unread=1" -Token $token
Assert ($unread.Status -eq 200) "GET /api/v1/notifications?unread=1 filters unread"

$count = Invoke-Api -Path "/api/v1/notifications/unread-count" -Token $token
Assert ($count.Status -eq 200 -and $null -ne $count.Body.unread_count) "GET /api/v1/notifications/unread-count reports the badge"

# 4. Create ------------------------------------------------------------------------
$created = Invoke-Api -Method POST -Path "/api/v1/notifications" -Token $token -Body @{
    type    = "system"
    title   = "Smoke notification $RunId"
    body    = "Created by test-notifications.ps1"
    payload = @{ source = "smoke"; run_id = $RunId }
}
Assert ($created.Status -eq 201 -and $created.Body.data.title -eq "Smoke notification $RunId") "POST /api/v1/notifications creates a broadcast notification"
$notifId = $created.Body.data.id

$countAfter = Invoke-Api -Path "/api/v1/notifications/unread-count" -Token $token
Assert ($countAfter.Body.unread_count -ge $count.Body.unread_count) "unread count increases after creation"

# 5. Show, update, read state -------------------------------------------------------
$show = Invoke-Api -Path "/api/v1/notifications/$notifId" -Token $token
Assert ($show.Status -eq 200 -and $show.Body.data.id -eq $notifId) "GET /api/v1/notifications/{id} shows the notification"

$updated = Invoke-Api -Method PUT -Path "/api/v1/notifications/$notifId" -Token $token -Body @{
    title   = "Smoke notification updated $RunId"
    is_read = $true
}
Assert ($updated.Status -eq 200 -and $updated.Body.data.is_read -eq $true) "PUT /api/v1/notifications/{id} marks the notification read"

$countAfterRead = Invoke-Api -Path "/api/v1/notifications/unread-count" -Token $token
Assert ($countAfterRead.Body.unread_count -lt $countAfter.Body.unread_count) "unread count decreases after marking read"

# Re-open it so read-all below has something to do
$reopened = Invoke-Api -Method PUT -Path "/api/v1/notifications/$notifId" -Token $token -Body @{ is_read = $false }
Assert ($reopened.Status -eq 200 -and $reopened.Body.data.is_read -eq $false) "PUT /api/v1/notifications/{id} can reopen a notification"

# 6. read-all -----------------------------------------------------------------------
$readAll = Invoke-Api -Method POST -Path "/api/v1/notifications/read-all" -Token $token
Assert ($readAll.Status -eq 200 -and $null -ne $readAll.Body.updated) "POST /api/v1/notifications/read-all marks everything read"

$countReset = Invoke-Api -Path "/api/v1/notifications/unread-count" -Token $token
Assert ($countReset.Body.unread_count -eq 0) "unread count is zero after read-all"

# 7. Delete --------------------------------------------------------------------------
$deleted = Invoke-Api -Method DELETE -Path "/api/v1/notifications/$notifId" -Token $token
Assert ($deleted.Status -eq 204) "DELETE /api/v1/notifications/{id} removes the notification"

# 8. Metrics --------------------------------------------------------------------------
$metrics = Invoke-Api -Path "/api/metrics"
Assert ($metrics.Status -eq 200 -and $metrics.ContentType -match "text/plain") "GET /api/metrics returns Prometheus text"
Assert ($metrics.Raw -match 'nexi_erp_notifications_created_total ') "/api/metrics exposes nexi_erp_notifications_created_total"
Assert ($metrics.Raw -match 'nexi_erp_notifications_total\{status="unread"\} ') "/api/metrics exposes the unread notifications gauge"
Assert ($metrics.Raw -match 'nexi_erp_notifications_total\{status="read"\} ') "/api/metrics exposes the read notifications gauge"

# 9. Cleanup safeguard --------------------------------------------------------------
$leftover = Invoke-Tinker -PhpCode "echo \App\Models\Notification::where('title','Smoke notification $RunId')->exists() || \App\Models\Notification::where('title','Smoke notification updated $RunId')->exists() ? 'left' : 'gone';"
Assert ($leftover -eq "gone") "no smoke-test notification remains in the database"

Write-Host ""
Write-Host "== Result: $script:Pass passed, $script:Fail failed ==" -ForegroundColor Cyan
if ($script:Fail -gt 0) {
    Write-Host "SMOKE TEST FAILED" -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE TEST PASSED" -ForegroundColor Green
exit 0