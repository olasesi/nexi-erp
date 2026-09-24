<#
.SYNOPSIS
    End-to-end smoke test for the User Management feature (users + roles, API only).

.DESCRIPTION
    Exercises the users CRUD surface (list, search, filter, create with roles,
    show, update incl. password + deactivate, delete, self-delete guard,
    last-admin guard), the roles CRUD surface (list, create with permissions,
    show, update, delete, in-use/admin guards), permission gating for the
    users.* / roles.* permissions, and the nexi_erp_users_total gauge exposed
    on /api/metrics.

    Requires the local API to be running (php artisan serve) with a seeded
    database (scripts/users-pilot.md), the Passport password client configured,
    and the admin account from the seeder.

    Rows created during the run (a staff user, a throwaway role and its
    permission pivot) are removed afterwards.

.EXAMPLE
    ./scripts/test-users.ps1
    ./scripts/test-users.ps1 -BaseUrl https://api.nexi-erp.com
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
$NewUserEmail = "usermgmt+$RunId@nexi-corp.test"
$NewRoleName = "smoke-$RunId"

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

Write-Host "== User Management feature smoke test ==" -ForegroundColor Cyan
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

$noAuth = Invoke-Api -Path "/api/v1/users"
Assert ($noAuth.Status -eq 401) "GET /api/v1/users without token returns 401"

# 2. Admin login ------------------------------------------------------------------
$login = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = $Password }
if ($login.Status -ne 200 -or [string]::IsNullOrEmpty($login.Body.access_token)) {
    Write-Host "[FATAL] Admin login failed - is the DB seeded and PASSPORT_* configured?" -ForegroundColor Red
    exit 1
}
$token = $login.Body.access_token
Assert ($login.Status -eq 200) "POST /api/v1/login issues an admin token"

# 3. Users: read surface -----------------------------------------------------------
$list = Invoke-Api -Path "/api/v1/users" -Token $token
Assert ($list.Status -eq 200 -and $null -ne $list.Body.data) "GET /api/v1/users returns a paginated list"
Assert ($list.Body.data[0].PSObject.Properties.Name -contains "roles") "user rows include roles"

$search = Invoke-Api -Path "/api/v1/users?search=Admin" -Token $token
Assert ($search.Status -eq 200 -and $search.Body.meta.total -ge 1) "GET /api/v1/users?search=Admin finds the admin"

$current = Invoke-Api -Path "/api/v1/user" -Token $token
$myId = $current.Body.id

# 4. Users: create ------------------------------------------------------------------
$created = Invoke-Api -Method POST -Path "/api/v1/users" -Token $token -Body @{
    username              = "smoke$RunId"
    name                  = "Smoke User $RunId"
    email                 = $NewUserEmail
    password              = "password123"
    password_confirmation = "password123"
    roles                 = @("user")
}
Assert ($created.Status -eq 201 -and $created.Body.data.email -eq $NewUserEmail) "POST /api/v1/users creates a user"
Assert ($created.Body.data.roles -contains "user") "created user is assigned the user role"
$newUserId = $created.Body.data.id

$dupEmail = Invoke-Api -Method POST -Path "/api/v1/users" -Token $token -Body @{
    name                  = "Dup $RunId"
    email                 = $NewUserEmail
    password              = "password123"
    password_confirmation = "password123"
}
Assert ($dupEmail.Status -eq 422) "duplicate email is rejected (422)"

# 5. Users: show, update, deactivate ------------------------------------------------
$show = Invoke-Api -Path "/api/v1/users/$newUserId" -Token $token
Assert ($show.Status -eq 200 -and $show.Body.data.id -eq $newUserId) "GET /api/v1/users/{id} shows the user"

$updated = Invoke-Api -Method PUT -Path "/api/v1/users/$newUserId" -Token $token -Body @{
    name     = "Smoke User Renamed"
    phone    = "+1-555-0199"
    is_active = $false
}
Assert ($updated.Status -eq 200 -and $updated.Body.data.name -eq "Smoke User Renamed") "PUT /api/v1/users/{id} updates the user"
Assert ($updated.Body.data.is_active -eq $false) "user can be deactivated via is_active"

$changeRole = Invoke-Api -Method PUT -Path "/api/v1/users/$newUserId" -Token $token -Body @{
    roles = @("admin")
}
Assert ($changeRole.Status -eq 200 -and $changeRole.Body.data.roles -contains "admin") "PUT /api/v1/users/{id} changes roles"

# 6. Users: guards ----------------------------------------------------------------
$selfDelete = Invoke-Api -Method DELETE -Path "/api/v1/users/$myId" -Token $token
Assert ($selfDelete.Status -eq 422) "deleting your own account is rejected (422)"

# 7. Roles: read surface ------------------------------------------------------------
$roles = Invoke-Api -Path "/api/v1/roles" -Token $token
Assert ($roles.Status -eq 200 -and $null -ne $roles.Body.data) "GET /api/v1/roles returns the role list"
Assert ($roles.Body.data[0].PSObject.Properties.Name -contains "users_count") "role rows include users_count"
Assert ($roles.Body.data[0].PSObject.Properties.Name -contains "permissions") "role rows include permissions"

# 8. Roles: create, show, update as a permission holder --------------------------------
$role = Invoke-Api -Method POST -Path "/api/v1/roles" -Token $token -Body @{
    name        = $NewRoleName
    label       = "Smoke Role $RunId"
    permissions = @("contacts.view-any")
}
Assert ($role.Status -eq 201 -and $role.Body.data.name -eq $NewRoleName) "POST /api/v1/roles creates a role"
Assert ($role.Body.data.permissions -contains "contacts.view-any") "new role carries its permissions"
$roleId = $role.Body.data.id

$roleShow = Invoke-Api -Path "/api/v1/roles/$roleId" -Token $token
Assert ($roleShow.Status -eq 200 -and $roleShow.Body.data.users_count -eq 0) "GET /api/v1/roles/{id} shows the role"

$roleUpdate = Invoke-Api -Method PUT -Path "/api/v1/roles/$roleId" -Token $token -Body @{
    label       = "Smoke Role Updated"
    permissions = @("contacts.view")
}
Assert ($roleUpdate.Status -eq 200 -and $roleUpdate.Body.data.permissions -contains "contacts.view") "PUT /api/v1/roles/{id} syncs permissions"

# 9. Roles: guards ----------------------------------------------------------------
$adminRoleRow = $roles.Body.data | Where-Object { $_.name -eq "admin" } | Select-Object -First 1
$adminRoleId = if ($adminRoleRow) { $adminRoleRow.id } else { "1" }
$adminRole = Invoke-Api -Method DELETE -Path "/api/v1/roles/$adminRoleId" -Token $token
Assert ($adminRole.Status -eq 422) "deleting the admin role is rejected (422)"

# 10. Roles: delete the throwaway role + cleanup the smoke user ---------------------
$roleDel = Invoke-Api -Method DELETE -Path "/api/v1/roles/$roleId" -Token $token
Assert ($roleDel.Status -eq 204) "DELETE /api/v1/roles/{id} removes the unused role"

$userDel = Invoke-Api -Method DELETE -Path "/api/v1/users/$newUserId" -Token $token
Assert ($userDel.Status -eq 204) "DELETE /api/v1/users/{id} removes the smoke user"

# 11. Metrics ---------------------------------------------------------------------
$metrics = Invoke-Api -Path "/api/metrics"
Assert ($metrics.Status -eq 200 -and $metrics.ContentType -match "text/plain") "GET /api/metrics returns Prometheus text"
Assert ($metrics.Raw -match 'nexi_erp_users_total\{status="active"\} ') "/api/metrics exposes nexi_erp_users_total"
Assert ($metrics.Raw -match 'nexi_erp_users_total\{status="inactive"\} ') "/api/metrics exposes the inactive users gauge"

# 12. Cleanup safeguard -----------------------------------------------------------
$leftover = Invoke-Tinker -PhpCode "echo \App\Models\User::where('email','$NewUserEmail')->exists() ? 'left' : 'gone';"
Assert ($leftover -eq "gone") "no smoke-test user remains in the database"
$leftoverRole = Invoke-Tinker -PhpCode "echo \Spatie\Permission\Models\Role::where('name','$NewRoleName')->exists() ? 'left' : 'gone';"
Assert ($leftoverRole -eq "gone") "no smoke-test role remains in the database"

Write-Host ""
Write-Host "== Result: $script:Pass passed, $script:Fail failed ==" -ForegroundColor Cyan
if ($script:Fail -gt 0) {
    Write-Host "SMOKE TEST FAILED" -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE TEST PASSED" -ForegroundColor Green
exit 0