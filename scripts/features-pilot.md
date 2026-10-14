# Feature Taxonomy, Sidebar Naming & Permissions — Pilot Runbook

This slice defines **how every business feature maps to a sidebar entry and a
permission module** so the frontend can render the navigation from the API's
permission set instead of hard-coding keys. It merges the two reference products
the product team researched (UltimatePOS and WorkDo Dash) into one consistent
naming scheme ("Blend").

The rule is simple: **one sidebar item (group or entry) ⇒ one permission module**,
and `EnsurePermitted` derives the permission name from the route name
(`{module}.{action}`), so gating follows the sidebar automatically.

---

## 1. Sidebar ↔ module ↔ routes

| Sidebar group | Entry / module | Routes |
|---|---|---|
| Users & Roles | `users`, `roles` | `/api/v1/users`, `/api/v1/roles` |
| Contacts | `contacts` | `/api/v1/contacts` |
| Products | `product-categories`, `products`, `warehouses` | `/api/v1/product-categories`, `/api/v1/products`, `/api/v1/warehouses` |
| Purchases | `purchase-orders`, `purchase-receipts` | `/api/v1/purchase-orders`, `/api/v1/purchase-receipts` |
| Sales | `sales-orders`, `quotations`, `invoices` | `/api/v1/sales-orders`, `/api/v1/quotations`, `/api/v1/invoices` |
| Point of Sale | `pos-registers`, `pos-orders` | `/api/v1/pos-registers`, `/api/v1/pos-orders` (+ `GET /api/v1/pos-orders/summary`, `GET /api/v1/dashboard/pos-summary`) |
| CRM | `leads`, `deals`, `crm-stages` | `/api/v1/leads`, `/api/v1/deals`, `/api/v1/crm-stages` (+ `POST /api/v1/leads/{lead}/convert`, `GET /api/v1/dashboard/crm-summary`) |
| Stock | `stock-adjustments`, `stock-transfers` | `/api/v1/stock-adjustments`, `/api/v1/stock-transfers` |
| Expenses | `expense-categories`, `expenses`, `income-categories`, `incomes` | `/api/v1/expense-categories`, `/api/v1/expenses`, `/api/v1/income-categories`, `/api/v1/incomes` |
| Payment Accounts | `payments`, `bank-accounts`, `bank-transactions` | `/api/v1/payments`, `/api/v1/bank-accounts`, `/api/v1/bank-transactions` |
| Accounting | `chart-of-accounts`, `journal-entries`, `reconciliations` | `/api/v1/chart-of-accounts`, `/api/v1/journal-entries`, `/api/v1/reconciliations` |
| Companies | `companies`, `business-locations` | `/api/v1/companies`, `/api/v1/business-locations` |
| Dashboard | `dashboard` | `GET /api/v1/dashboard` |
| Reports | `reports` | `GET /api/v1/reports/*` |
| Notifications | `notifications` | `/api/v1/notifications*` (in-app inbox + bell) |
| Settings | `settings`, `business-settings` | `/api/v1/settings*`, `/api/v1/business-settings*` |
| Integrations | `integrations` | `/api/v1/integrations*` (provider connectors: catalog, credentials, connect/disconnect/test) |
| Webhooks | `webhook-endpoints`, `webhook-deliveries`, `webhook-events` | `/api/v1/webhook-endpoints*`, `/api/v1/webhook-deliveries*`, `/api/v1/webhook-events` (outbound subscriptions + delivery log) |

Merges kept from the research:
- **Settings top level**: WorkDo's Dash settings and UltimatePOS's Business
  Settings both live under one "Settings" group; the API exposes two read-mostly
  surfaces (`settings` = app/profile theme etc., `business-settings` = company
  profile, tax, invoice, system config).
- **Payment Accounts** groups UltimatePOS's Payment Accounts (bank accounts +
  transactions/matching) with the payments ledger surface.
- **Accounting** collects chart of accounts, journal entries and bank
  reconciliations — the "double-entry" bucket that WorkDo puts in Finance.
- **Stock** holds UltimatePOS's Stock Transfer + Stock Adjustment screens.

### Add-ons (per-company toggle)

Some modules are opt-in **add-ons**: `GET /api/v1/addons` lists them and each company
toggles them independently. A switched-off add-on answers `403 {code:"addon_disabled"}`
before the permission check. Everything derives from one `config/addons.php` entry —
catalog listing, toggle state, permission seeding (`module` + `actions`) and route
gating (`Route::middleware('addon:<id>')`).

| Add-on id | Modules | Routes |
|---|---|---|
| `coworking-space` | `coworking-units`, `coworking-bookings` | `/api/v1/coworking-*` |
| `laundry-dry-cleaning` | `laundry-orders`, `laundry-items` | `/api/v1/laundry-*` |
| `gym-management` | `gym-plans`, `gym-members`, `gym-visits` | `/api/v1/gym-*` |
| `rental` | `rental-agreements`, `rental-items` | `/api/v1/rental-*` |
| `form-builder` | `forms`, `form-fields`, `form-submissions` | `/api/v1/forms`, `/api/v1/form-fields`, `/api/v1/form-submissions` |
| `messenger` | `conversations`, `messages` | `/api/v1/conversations`, `/api/v1/messages` |
| `document-manager` | `document-folders`, `documents` | `/api/v1/document-folders*`, `/api/v1/documents*` (+ `POST /documents/{id}/share`, `POST /documents/{id}/restore`, `POST /document-folders/{id}/restore`) |
| `support-tickets` | `tickets`, `ticket-replies` | `/api/v1/tickets`, `/api/v1/ticket-replies` |
| `calendar-events` | `events`, `event-attendees` | `/api/v1/events`, `/api/v1/event-attendees` |
| `asset-management` | `asset-categories`, `assets`, `asset-assignments` | `/api/v1/asset-categories`, `/api/v1/assets`, `/api/v1/asset-assignments` |
| `smart-reports` | `smart-reports` | `/api/v1/smart-reports*` (+ `POST /api/v1/smart-reports/{smart_report}/run`) |
| `ai-agent`, `ai-document`, `ai-image` | `ai-agent-threads`, `ai-agent-messages`, `ai-agent-prompts`, `ai-documents`, `ai-images` | `/api/v1/ai-agent-threads*` (+ nested `/messages`), `/api/v1/ai-documents*`, `/api/v1/ai-images*`, `/api/v1/ai-agent-messages*`, `/api/v1/ai-agent-prompts*` |
| `goals` | `goals`, `goal-key-results` | `/api/v1/goals*` (+ `progress`, `cancel`), `/api/v1/goal-key-results*` |
| `budget-planner` | `budgets`, `budget-lines` | `/api/v1/budgets*` (+ `approve`, `close`), `/api/v1/budget-lines*` |
| `training` | `trainings`, `training-types` | `/api/v1/trainings*` (+ `cancel`), `/api/v1/training-types*` |
| `youtube` | `youtube-videos`, `youtube-playlists`, `youtube-channel`, `youtube-activity` | `/api/v1/youtube-*` |
| `performance` | `performance-reviews` | `/api/v1/performance-reviews*` (+ `approve`, `reopen`) |
| `performance-indicator` | `performance-indicators` | `/api/v1/performance-indicators*` |
| `recruitment` | `job-postings`, `job-candidates`, `job-applications` | `/api/v1/job-postings*`, `/api/v1/job-candidates*`, `/api/v1/job-applications*` (+ `hire`) |
| `job-search` | `job-openings` | `/api/v1/job-openings*` |
| `work-permit` | `work-permits` | `/api/v1/work-permits*` (+ `renew`) |
| `rotas` | `rotas`, `rota-entries` | `/api/v1/rotas*` (+ `publish`), `/api/v1/rota-entries*` |
| `commission` | `commissions` | `/api/v1/commissions*` (+ `approve`, `mark-paid`) |
| `time-tracker` | `time-entries` | `/api/v1/time-entries*` (+ `start`, `stop`) |
| `timesheet` | `timesheets`, `timesheet-entries` | `/api/v1/timesheets*` (+ `submit`, `approve`, `reject`), `/api/v1/timesheet-entries*` |
| `team-workload` | `team-workload` | `GET /api/v1/team-workload` (+ `/summary`) |
| `digital-certificate` | `digital-certificates` | `/api/v1/digital-certificates*` (+ `generate`, `revoke`) |
| `proposal` | `proposals`, `proposal-items` | `/api/v1/proposals*` (+ `send`, `accept`, `reject`, `convert`), `/api/v1/proposal-items*` |
| `sales-target` | `sales-targets` | `/api/v1/sales-targets*` (+ `progress`) |
| `sales-return` | `sales-returns`, `sales-return-items` | `/api/v1/sales-returns*` (+ `complete`), `/api/v1/sales-return-items*` |
| `credit-note` | `credit-notes`, `credit-note-items` | `/api/v1/credit-notes*` (+ `approve`, `apply`), `/api/v1/credit-note-items*` |
| `debit-note` | `debit-notes`, `debit-note-items` | `/api/v1/debit-notes*` (+ `approve`, `apply`), `/api/v1/debit-note-items*` |
| `sales-agent` | `sales-agents`, `sales-agent-orders` | `/api/v1/sales-agents*` (+ `request`, `accept`, `reject`), `/api/v1/sales-agent-orders*` (+ `update-status`) |
| `customer-group` | `customer-groups` | `/api/v1/customer-groups*` |
| `product-unit` | `product-units` | `/api/v1/product-units*` |
| `deal-activity` | `deal-activities` | `/api/v1/deal-activities*` (+ `complete`) |
| `announcement` | `announcements` | `/api/v1/announcements*` (+ `publish`, `archive`) |
| `todo` | `todos` | `/api/v1/todos*` (+ `complete`) |
| `knowledge-base` | `kb-categories`, `kb-articles` | `/api/v1/kb-categories*`, `/api/v1/kb-articles*` (+ `publish`) |
| `note` | `notes` | `/api/v1/notes*` |
| `award` | `awards` | `/api/v1/awards*` |
| `company-policy` | `company-policies` | `/api/v1/company-policies*` (+ `publish`, `archive`) |
| `bookings` | `booking-resources`, `bookings` | `/api/v1/booking-resources*`, `/api/v1/bookings*` (+ `confirm`, `check-in`, `complete`, `cancel`, `no-show`) |

The cross-domain tools (`form-builder` … `asset-management`) are documented in
`scripts/tools-pilot.md`; the Phase 1 add-ons (`smart-reports` … `youtube`) in
`scripts/phase1-pilot.md`; the HRM suite (`performance` … `digital-certificate`)
in `scripts/phase2-pilot.md`; the sales/CRM extras (`proposal` … `deal-activity`)
in `scripts/phase3-pilot.md`; the collaboration add-ons (`announcement` …
`company-policy`) in `scripts/phase4-pilot.md`; the bookings add-on
(`bookings`) in `scripts/phase5-pilot.md`; the Vertical Solutions
(`coworking-space` … `rental`) in `scripts/phase11-pilot.md`; the core Point of
Sale surface (`pos-registers`/`pos-orders`) in `scripts/phase12-pilot.md`; the
outbound webhooks surface in `scripts/phase13-pilot.md`; the core Stock &
Warehousing surfaces (`warehouses`/`stock-adjustments`/`stock-transfers`) in
`scripts/phase14-pilot.md`; the core Banking & Reconciliation surfaces
(`bank-accounts`/`bank-transactions`/`reconciliations`) in
`scripts/phase15-pilot.md`; the core Currencies & FX surface
(`currencies`/`currency-rates`) in `scripts/phase16-pilot.md`; the core Product
catalogue (`product-categories`/`products`, incl. CSV import/export) in
`scripts/phase17-pilot.md`; the core Purchasing flow (`purchase-orders` /
`purchase-receipts`) in `scripts/phase18-pilot.md`; the core Sales & Invoicing
documents (`sales-orders`/`quotations`/`invoices`) in `scripts/phase19-pilot.md`;
the core Payments surface (`payments`) in `scripts/phase20-pilot.md`; the core
Expenses & Income surfaces (`expense-categories`/`expenses`/`income-categories`/
`incomes`) in `scripts/phase21-pilot.md`.
The core Projects & HRM
surfaces are documented in
`scripts/phase10-pilot.md`.

### Integrations connectors (settings-gated)

A separate, **non add-on** surface (`integrations` permission module) turns the
provider catalog in `config/business-integrations.php` into a connectable
connector list. Credentials live in the matching `business-settings` group;
connection state lives on the `integrations` table. Nothing is ever called out
to — `connect` validates that every credential is present and `test` reports the
stored state. See `scripts/phase6-pilot.md`.

---

## 2. Permission model

`app/Http/Middleware/EnsurePermitted.php` maps a route name to a permission:

| Route name action | Permission suffix |
|---|---|
| `index` | `view-any` |
| `show` | `view` |
| `store` | `create` |
| `update` | `update` |
| `destroy` | `delete` |
| custom actions (`complete`, `post`, `void`, `import`, `accept`, `reject`, `cancel`, `match`, `unmatch`, `read-all`, `cache-clear`, `email-test`, report/`summary`) | `update` / `create` / `view` / `view-any` as appropriate |

Enforcement is **data-driven**: a route is only gated while its permission row
exists (seeded by `RolesAndPermissionsSeeder`). Add/remove a permission to toggle
a whole module without code changes.

### Seeded role perms (admin · manager · user)

- **admin** — every permission (`Permission::all()`).
- **manager** — everything except `*.delete` and `manage-roles` /
  `manage-permissions`.
- **user** — `contacts.*` (view-any/view/create/update), `products.*` view,
  `sales-orders.*` view-any/view/create/update, plus `dashboard.*` view and
  `notifications.*` view-any/view/update (standard staff can see the bell and mark
  their inbox read).
- **customer** — `portal.access` only (customer portal, blocked from the staff
  surface by `EnsureStaffAccount` + `staff` middleware).

---

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php artisan serve

# repo root
.\scripts\test-notifications.ps1    # notifications inbox e2e
.\scripts\test-users.ps1            # users/roles e2e (regression)
```

Check the sidebar source of truth (per-mission gating) with the admin token:

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }
Invoke-RestMethod http://localhost:8000/api/v1/dashboard -Headers $h
Invoke-WebRequest http://localhost:8000/api/metrics | Select-String "nexi_erp"
```

## 4. Frontend consumption

The sidebar contract is: fetch `GET /api/v1/user`. It returns the authenticated
user with `company`, and enriched `roles` (array of role names) and `permissions`
(array of effective permission names, already flattened across the user's roles). 

Build the menu from the module column above, render the label from the Sidebar
group/Entry columns, and hide anything the active user lacks `view-any` for. Since
permissions arrive flattened, the frontend needs no role logic:

```js
const { roles, permissions } = await api.get('/api/v1/user');
// e.g. show the Stock group only if permissions includes 'stock-adjustments.view-any'
```

## 5. Order of operations when adding a future feature

1. Add the module to `RolesAndPermissionsSeeder::$modules`.
2. Give every new route a name (`{module}.{action}`) and, if it is a custom action,
   add its `ACTION_MAP` entry in `EnsurePermitted`.
3. Add a per-feature `Store/Update` request + Resource + Controller + tests.
4. Ship with the deploy/scale/monitor baseline from `notifications-pilot.md`.