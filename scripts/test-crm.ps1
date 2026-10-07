<#
.SYNOPSIS
    End-to-end smoke test for the CRM feature (leads + pipeline stages + deals, API only).

.DESCRIPTION
    Exercises the CRM surface against a running API: pipeline stage CRUD (append,
    show with deals_count, delete guards), lead CRUD + funnel filters/search,
    lead conversion into a contact + an open deal (incl. idempotency and guards),
    deal CRUD + stage movement + closing, the dashboard crm-summary card feed,
    and the nexi_erp_leads_total / deals_total / pipeline_value metrics.

    Requires the local API to be running (php artisan serve) with a seeded
    database (scripts/crm-pilot.md), the Passport password client configured,
    and the admin account from the seeder.

    Rows created during the run (stages, lead, deal, converted contact) are
    removed afterwards.

.EXAMPLE
    ./scripts/test-crm.ps1
    ./scripts/test-crm.ps1 -BaseUrl https://api.nexi-erp.com
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

Write-Host "== CRM feature smoke test ==" -ForegroundColor Cyan
Write-Host "Base URL : $BaseUrl"
Write-Host "Run ID   : $RunId"
Write-Host ""

# 1. Health + unauthenticated ----------------------------------------------------
$health = Invoke-Api -Path "/api/health"
Assert ($health.Status -eq 200 -and $health.Body.status -eq "healthy") "GET /api/health returns healthy"

$noAuth = Invoke-Api -Path "/api/v1/leads"
Assert ($noAuth.Status -eq 401) "GET /api/v1/leads without token returns 401"

# 2. Admin login ------------------------------------------------------------------
$login = Invoke-Api -Method POST -Path "/api/v1/login" -Body @{ email = $Email; password = $Password }
if ($login.Status -ne 200 -or [string]::IsNullOrEmpty($login.Body.access_token)) {
    Write-Host "[FATAL] Admin login failed - is the DB seeded and PASSPORT_* configured?" -ForegroundColor Red
    exit 1
}
$token = $login.Body.access_token
Assert ($login.Status -eq 200) "POST /api/v1/login issues an admin token"

$companies = Invoke-Api -Path "/api/v1/companies" -Token $token
$c = [int]$companies.Body.data[0].id
Assert ($c -gt 0) "an admin company is available for the run"

# 3. Pipeline stages ----------------------------------------------------------------
$stageA = Invoke-Api -Method POST -Path "/api/v1/crm-stages" -Token $token -Body @{
    company_id = $c
    name       = "Qualified-$RunId"
    position   = 2
}
Assert ($stageA.Status -eq 201 -and $stageA.Body.data.name -eq "Qualified-$RunId") "POST /api/v1/crm-stages creates a stage"
$stageAId = [int]$stageA.Body.data.id

$stageB = Invoke-Api -Method POST -Path "/api/v1/crm-stages" -Token $token -Body @{
    company_id = $c
    name       = "Negotiation-$RunId"
}
Assert ($stageB.Status -eq 201 -and [int]$stageB.Body.data.position -gt [int]$stageA.Body.data.position) "position auto-appends to the pipeline"
$stageBId = [int]$stageB.Body.data.id

$stageList = Invoke-Api -Path "/api/v1/crm-stages" -Token $token
Assert ($stageList.Status -eq 200 -and $null -ne $stageList.Body.data) "GET /api/v1/crm-stages returns the pipeline"
Assert ($stageList.Body.data[0].PSObject.Properties.Name -contains "deals_count") "stage rows expose deals_count"

# 4. Deals on a stage block its deletion ---------------------------------------------
$blockDeal = Invoke-Api -Method POST -Path "/api/v1/deals" -Token $token -Body @{
    company_id   = $c
    crm_stage_id = $stageAId
    title        = "Blocking deal $RunId"
    value        = 5000
}
Assert ($blockDeal.Status -eq 201 -and $blockDeal.Body.data.status -eq "open") "POST /api/v1/deals creates an open deal"
$blockDealId = [int]$blockDeal.Body.data.id

$blocked = Invoke-Api -Method DELETE -Path "/api/v1/crm-stages/$stageAId" -Token $token
Assert ($blocked.Status -eq 422) "deleting a stage that still carries deals is rejected (422)"

# 5. Leads ---------------------------------------------------------------------------
$lead = Invoke-Api -Method POST -Path "/api/v1/leads" -Token $token -Body @{
    company_id  = $c
    name        = "Priya Raman-$RunId"
    organization = "Raman Foods"
    email       = "priya$RunId@raman.example"
    source      = "referral"
    value       = 8500
}
Assert ($lead.Status -eq 201 -and $lead.Body.data.status -eq "new") "POST /api/v1/leads creates a lead"
Assert ($lead.Body.data.source -eq "referral" -and $lead.Body.data.created_at -ne $null) "lead carries its source and timestamps"
$leadId = [int]$lead.Body.data.id

$badStatus = Invoke-Api -Method POST -Path "/api/v1/leads" -Token $token -Body @{
    company_id = $c
    name       = "Bad Funnel $RunId"
    status     = "winning"
}
Assert ($badStatus.Status -eq 422) "unknown funnel status is rejected (422)"

$next = Invoke-Api -Method PUT -Path "/api/v1/leads/$leadId" -Token $token -Body @{ status = "qualified" }
Assert ($next.Status -eq 200 -and $next.Body.data.status -eq "qualified") "PUT /api/v1/leads/{id} moves the lead through the funnel"

$search = Invoke-Api -Path "/api/v1/leads?search=$RunId" -Token $token
Assert ($search.Status -eq 200 -and $search.Body.meta.total -ge 1) "GET /api/v1/leads?search= finds the lead"

# 6. Convert: needs a pipeline first ---------------------------------------------------
# Free the first stage again so every pipeline guard is exercised in order.
$freeDeal = Invoke-Api -Method DELETE -Path "/api/v1/deals/$blockDealId" -Token $token
Assert ($freeDeal.Status -eq 204) "DELETE /api/v1/deals/{id} removes the blocking deal"
$freeStage = Invoke-Api -Method DELETE -Path "/api/v1/crm-stages/$stageAId" -Token $token
Assert ($freeStage.Status -eq 204) "DELETE /api/v1/crm-stages/{id} removes an empty stage"

$stageC = Invoke-Api -Method POST -Path "/api/v1/crm-stages" -Token $token -Body @{
    company_id = $c
    name       = "Fresh-$RunId"
}
$stageCId = [int]$stageC.Body.data.id

$converted = Invoke-Api -Method POST -Path "/api/v1/leads/$leadId/convert" -Token $token
Assert ($converted.Status -eq 200 -and $converted.Body.data.status -eq "converted") "POST /api/v1/leads/{id}/convert promotes the lead"
Assert ($null -ne $converted.Body.data.contact.id) "conversion creates a contact"
$contactId = [int]$converted.Body.data.contact.id
$newDeals = @($converted.Body.data.deals)
Assert ($newDeals.Count -ge 1) "conversion drops the lead onto the pipeline"
$dealId = [int]$newDeals[0].id

$again = Invoke-Api -Method POST -Path "/api/v1/leads/$leadId/convert" -Token $token
Assert ($again.Status -eq 422) "converting an already-converted lead is rejected (422)"

# 7. Deals: list, move, close -------------------------------------------------------------
$dealList = Invoke-Api -Path "/api/v1/deals?status=open" -Token $token
Assert ($dealList.Status -eq 200 -and $null -ne $dealList.Body.data) "GET /api/v1/deals?status=open lists open deals"

$moved = Invoke-Api -Method PUT -Path "/api/v1/deals/$dealId" -Token $token -Body @{ crm_stage_id = $stageBId }
Assert ($moved.Status -eq 200 -and [int]$moved.Body.data.crm_stage_id -eq $stageBId) "PUT /api/v1/deals/{id} moves a deal across stages"

$won = Invoke-Api -Method PUT -Path "/api/v1/deals/$dealId" -Token $token -Body @{ status = "won" }
Assert ($won.Status -eq 200 -and $won.Body.data.status -eq "won") "PUT /api/v1/deals/{id} closes a deal as won"

# 8. Dashboard CRM card ---------------------------------------------------------------------
$card = Invoke-Api -Path "/api/v1/dashboard/crm-summary?company_id=$c" -Token $token
Assert ($card.Status -eq 200 -and $null -ne $card.Body.pipeline_by_stage) "GET /api/v1/dashboard/crm-summary returns the tile feed"
Assert ($card.Body.active_leads -ge 0 -and $card.Body.conversion_rate -ge 0) "the CRM card carries funnel numbers"

# 9. Metrics -----------------------------------------------------------------------------
$metrics = Invoke-Api -Path "/api/metrics"
Assert ($metrics.Status -eq 200 -and $metrics.ContentType -match "text/plain") "GET /api/metrics returns Prometheus text"
Assert ($metrics.Raw -match 'nexi_erp_leads_total\{status="converted"\} [1-9]') "/api/metrics exposes nexi_erp_leads_total"
Assert ($metrics.Raw -match 'nexi_erp_deals_total\{status="won"\} [1-9]') "/api/metrics exposes nexi_erp_deals_total"
Assert ($metrics.Raw -match 'nexi_erp_pipeline_value_open') "/api/metrics exposes nexi_erp_pipeline_value_open"

# 10. Cleanup: deal, contact, converted lead, stages -----------------------------------------
$cleanups = @(
    @{ Path = "/api/v1/deals/$dealId";               Name = "deal" },
    @{ Path = "/api/v1/contacts/$contactId";         Name = "converted contact" },
    @{ Path = "/api/v1/leads/$leadId";               Name = "converted lead" },
    @{ Path = "/api/v1/crm-stages/$stageBId";        Name = "stage B" },
    @{ Path = "/api/v1/crm-stages/$stageCId";        Name = "stage C" }
)
foreach ($cleanup in $cleanups) {
    $del = Invoke-Api -Method DELETE -Path $cleanup.Path -Token $token
    Assert ($del.Status -eq 204) "DELETE $($cleanup.Path) removes the $($cleanup.Name)"
}

Write-Host ""
Write-Host "== Result: $script:Pass passed, $script:Fail failed ==" -ForegroundColor Cyan
if ($script:Fail -gt 0) {
    Write-Host "SMOKE TEST FAILED" -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE TEST PASSED" -ForegroundColor Green
exit 0