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
| Purchases | `purchase-orders` | `/api/v1/purchase-orders` |
| Sales | `sales-orders`, `quotations`, `invoices` | `/api/v1/sales-orders`, `/api/v1/quotations`, `/api/v1/invoices` |
| Stock | `stock-adjustments`, `stock-transfers` | `/api/v1/stock-adjustments`, `/api/v1/stock-transfers` |
| Expenses | `expense-categories`, `expenses`, `income-categories`, `incomes` | `/api/v1/expense-categories`, `/api/v1/expenses`, `/api/v1/income-categories`, `/api/v1/incomes` |
| Payment Accounts | `payments`, `bank-accounts`, `bank-transactions` | `/api/v1/payments`, `/api/v1/bank-accounts`, `/api/v1/bank-transactions` |
| Accounting | `chart-of-accounts`, `journal-entries`, `reconciliations` | `/api/v1/chart-of-accounts`, `/api/v1/journal-entries`, `/api/v1/reconciliations` |
| Companies | `companies`, `business-locations` | `/api/v1/companies`, `/api/v1/business-locations` |
| Dashboard | `dashboard` | `GET /api/v1/dashboard` |
| Reports | `reports` | `GET /api/v1/reports/*` |
| Notifications | `notifications` | `/api/v1/notifications*` (in-app inbox + bell) |
| Settings | `settings`, `business-settings` | `/api/v1/settings*`, `/api/v1/business-settings*` |

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