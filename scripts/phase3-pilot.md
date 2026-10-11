# Phase 3 Add-ons — Sales & CRM Extras — Pilot Runbook

Nine **sales and CRM add-ons** that extend the order-to-invoice core rebuilt in
Phase 0/1: proposals, targets, returns, adjustment notes, referral agents and a
handful of catalog/activity helpers. They follow the same `config/addons.php`
contract — one entry drives the catalog listing, the per-company toggle, the
permission seeding and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `proposal` | Proposal | `proposals`, `proposal-items` | `/api/v1/proposals*` (+ `send`, `accept`, `reject`, `convert`), `/api/v1/proposal-items*` |
| `sales-target` | Sales Target | `sales-targets` | `/api/v1/sales-targets*` (+ `progress`) |
| `sales-return` | Sales Return | `sales-returns`, `sales-return-items` | `/api/v1/sales-returns*` (+ `complete`), `/api/v1/sales-return-items*` |
| `credit-note` | Credit Note | `credit-notes`, `credit-note-items` | `/api/v1/credit-notes*` (+ `approve`, `apply`), `/api/v1/credit-note-items*` |
| `debit-note` | Debit Note | `debit-notes`, `debit-note-items` | `/api/v1/debit-notes*` (+ `approve`, `apply`), `/api/v1/debit-note-items*` |
| `sales-agent` | Sales Agent | `sales-agents`, `sales-agent-orders` | `/api/v1/sales-agents*` (+ `request`, `accept`, `reject`), `/api/v1/sales-agent-orders*` (+ `update-status`) |
| `customer-group` | Customer Group | `customer-groups` | `/api/v1/customer-groups*` |
| `product-unit` | Product Unit | `product-units` | `/api/v1/product-units*` |
| `deal-activity` | Deal Activity | `deal-activities` | `/api/v1/deal-activities*` (+ `complete`) |

## 1. Add-on contract

Identical to `tools-pilot.md`, `phase1-pilot.md` and `phase2-pilot.md` — catalog
(`GET /api/v1/addons`), toggle (`PUT /api/v1/addons/{id} {enabled:bool}`),
`addon:<id>` route gating returning `403 {code:"addon_disabled", addon:"<id>"}`,
and permission seeding from the entry's `module` + `actions` / `modules` list.
New `EnsurePermitted::ACTION_MAP` suffixes this phase relies on: `apply` →
`update`, `request` → `create`, `update-status` → `update` and `send` → `update`
(alongside the existing `convert`/`approve`/`reject`/`complete`/`progress`).

## 2. Invariants per add-on

- **Proposal** — a proposal carries line `proposal_items`; totals are recomputed
  from its items (`total = subtotal + tax - discount`). Lifecycle
  `draft → sent → accepted/rejected → converted`; `convert` only runs on an
  accepted proposal and, inside a DB transaction, writes an `Invoice` plus its
  `InvoiceItem` rows (generating the required invoice number) and links it via
  `invoice_id`.
- **Sales Target** — per-rep targets with a period; `progress` recomputes
  `achieved_amount` from sales orders created inside the period (scoped to the
  employee's linked user when present), accepts an explicit override, and flips
  `status` to `achieved` once the target is met. The resource exposes a clamped
  `progress_percentage`.
- **Sales Return** — a return against an invoice carries `sales_return_items`;
  saving an item recalculates totals. `complete` moves `draft → completed` and
  is refused otherwise.
- **Credit Note** — issued against a sales return; item edits recalculate
  `total` and `balance` (`total - applied_amount`). Lifecycle `draft → approved
  → applied`; `approve` stamps `approved_at`, `apply` consumes the full amount
  and zeroes the balance, each refusing an out-of-order transition.
- **Debit Note** — the vendor-side mirror of the credit note, linked to a
  purchase order; the same `draft → approved → applied` lifecycle.
- **Sales Agent** — a referral agent moves `pending → requested → active` via
  `request`/`accept`, or `requested → rejected`; each transition is guarded.
  `sales_agent_orders` derive `commission_amount = round(amount * rate / 100, 2)`
  when not supplied, and `update-status` moves an order through
  `pending → confirmed → delivered` (or `cancelled`).
- **Customer Group** — a named customer segment with a `discount_rate`
  (0–100) and an active flag.
- **Product Unit** — a unit of measure with an optional conversion to a base
  unit (`operator`: multiply/divide, `operation_value`); `convertsToBase()`
  applies the factor and guards divide-by-zero.
- **Deal Activity** — a logged note/call/meeting/email/task against a CRM deal
  (`crm_deals`); `complete` moves `pending → completed` and stamps
  `completed_at`, and is refused otherwise.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='ProposalTest|SalesTargetTest|SalesReturnTest|CreditNoteTest|DebitNoteTest|SalesAgentTest|CustomerGroupTest|ProductUnitTest|DealActivityTest'
```

## 4. API smoke test

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Proposal → accepted → converted to an invoice
$p = (Invoke-RestMethod http://localhost:8000/api/v1/proposals -Method Post -Headers $h -ContentType application/json `
       -Body (@{title="Website build";contact_id=1} | ConvertTo-Json)).data
Invoke-RestMethod "http://localhost:8000/api/v1/proposals/$($p.id)/send" -Method Post -Headers $h | Out-Null
Invoke-RestMethod "http://localhost:8000/api/v1/proposals/$($p.id)/accept" -Method Post -Headers $h | Out-Null
(Invoke-RestMethod "http://localhost:8000/api/v1/proposals/$($p.id)/convert" -Method Post -Headers $h).data.invoice_id

# Credit note lifecycle
$c = (Invoke-RestMethod http://localhost:8000/api/v1/credit-notes -Method Post -Headers $h -ContentType application/json `
       -Body (@{credit_note_number="CN-1";contact_id=1} | ConvertTo-Json)).data
Invoke-RestMethod "http://localhost:8000/api/v1/credit-notes/$($c.id)/approve" -Method Post -Headers $h | Out-Null
(Invoke-RestMethod "http://localhost:8000/api/v1/credit-notes/$($c.id)/apply" -Method Post -Headers $h).data.status  # applied

# Sales agent order commission
$a = (Invoke-RestMethod http://localhost:8000/api/v1/sales-agents -Method Post -Headers $h -ContentType application/json `
       -Body (@{name="Acme";commission_rate=5} | ConvertTo-Json)).data
(Invoke-RestMethod http://localhost:8000/api/v1/sales-agent-orders -Method Post -Headers $h -ContentType application/json `
  -Body (@{sales_agent_id=$a.id;order_number="SO-1";amount=1000} | ConvertTo-Json)).data.commission_amount  # 50

# Add-on guard
Invoke-RestMethod http://localhost:8000/api/v1/addons/proposal -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod http://localhost:8000/api/v1/proposals -Headers $h } catch { $_.ErrorDetails.Message }  # addon_disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve the
`laravel`-type docs at `/docs`). Every mutation is captured on `GET /api/v1/audit-logs`
and emitted to subscribed webhook endpoints.
