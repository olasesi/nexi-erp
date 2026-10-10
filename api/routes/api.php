<?php

use App\Http\Controllers\Api\ApiVersionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\V1\AddonController;
use App\Http\Controllers\Api\V1\AiAgentMessageController;
use App\Http\Controllers\Api\V1\AiAgentPromptController;
use App\Http\Controllers\Api\V1\AiAgentThreadController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AssetAssignmentController;
use App\Http\Controllers\Api\V1\AssetCategoryController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankTransactionController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\BudgetLineController;
use App\Http\Controllers\Api\V1\BusinessAdvisorController;
use App\Http\Controllers\Api\V1\BusinessLocationController;
use App\Http\Controllers\Api\V1\BusinessSettingsController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CoworkingBookingController;
use App\Http\Controllers\Api\V1\CoworkingUnitController;
use App\Http\Controllers\Api\V1\CrmStageController;
use App\Http\Controllers\Api\V1\CurrenciesController;
use App\Http\Controllers\Api\V1\CurrencyRateController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentFolderController;
use App\Http\Controllers\Api\V1\EventAttendeeController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\FormController;
use App\Http\Controllers\Api\V1\FormFieldController;
use App\Http\Controllers\Api\V1\FormSubmissionController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\GoalKeyResultController;
use App\Http\Controllers\Api\V1\GymMemberController;
use App\Http\Controllers\Api\V1\GymPlanController;
use App\Http\Controllers\Api\V1\GymVisitController;
use App\Http\Controllers\Api\V1\HrmAttendanceController;
use App\Http\Controllers\Api\V1\HrmDepartmentController;
use App\Http\Controllers\Api\V1\HrmDesignationController;
use App\Http\Controllers\Api\V1\HrmEmployeeController;
use App\Http\Controllers\Api\V1\HrmLeaveController;
use App\Http\Controllers\Api\V1\IncomeCategoryController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\LaundryItemController;
use App\Http\Controllers\Api\V1\LaundryOrderController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PosOrderController;
use App\Http\Controllers\Api\V1\PosRegisterController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMilestoneController;
use App\Http\Controllers\Api\V1\ProjectTaskController;
use App\Http\Controllers\Api\V1\PublicPaymentController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseReceiptController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\RentalAgreementController;
use App\Http\Controllers\Api\V1\RentalItemController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SmartReportController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SuggestionController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketReplyController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\TrainingTypeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WebhookDeliveryController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use App\Http\Controllers\Api\V1\YoutubeActivityController;
use App\Http\Controllers\Api\V1\YoutubeChannelController;
use App\Http\Controllers\Api\V1\YoutubePlaylistController;
use App\Http\Controllers\Api\V1\YoutubeVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/metrics', MetricsController::class);

Route::get('/versions', ApiVersionController::class);

Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('guest');

    Route::post('/customer/register', [CustomerPortalController::class, 'register'])
        ->middleware('guest');

    // Public payment links (token keyed, no authentication required).
    Route::prefix('public')->group(function () {
        Route::get('invoices/pay/{token}', [PublicPaymentController::class, 'show']);
        Route::post('invoices/pay/{token}', [PublicPaymentController::class, 'store']);
    });

    // Password self-service (staff and customers both authenticate here).
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
        ->middleware('guest');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('guest');
    Route::post('/password/change', [AuthController::class, 'changePassword'])
        ->middleware('auth:api');

    Route::middleware(['auth:api', 'customer'])->prefix('customer')->group(function () {
        Route::get('me', [CustomerPortalController::class, 'me']);
        Route::get('invoices', [CustomerPortalController::class, 'invoices']);
        Route::get('invoices/{invoice}', [CustomerPortalController::class, 'showInvoice']);
        Route::get('quotations', [CustomerPortalController::class, 'quotations']);
        Route::get('quotations/{quotation}', [CustomerPortalController::class, 'showQuotation']);
        Route::get('sales-orders', [CustomerPortalController::class, 'salesOrders']);
        Route::get('sales-orders/{sales_order}', [CustomerPortalController::class, 'showSalesOrder']);
        Route::get('payments', [CustomerPortalController::class, 'payments']);
        Route::get('payments/{payment}', [CustomerPortalController::class, 'showPayment']);
    });

    Route::middleware(['auth:api', 'staff', 'permitted'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        // Self-service profile edit, left unnamed like GET /user so it stays
        // outside the admin users.* permission gate: any staff may edit their
        // own name, username, and phone.
        Route::put('/user', [AuthController::class, 'updateProfile']);

        Route::apiResource('companies', CompanyController::class);
        Route::post('companies/{company}/restore', [CompanyController::class, 'restore'])->name('companies.restore');
        Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');
        Route::get('contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::apiResource('contacts', ContactController::class);
        Route::post('contacts/{contact}/restore', [ContactController::class, 'restore'])->name('contacts.restore');
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::post('product-categories/{product_category}/restore', [ProductCategoryController::class, 'restore'])->name('product-categories.restore');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::apiResource('warehouses', WarehouseController::class);
        Route::post('warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])->name('warehouses.restore');
        Route::apiResource('sales-orders', SalesOrderController::class);
        Route::post('sales-orders/{sales_order}/restore', [SalesOrderController::class, 'restore'])->name('sales-orders.restore');
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/restore', [PurchaseOrderController::class, 'restore'])->name('purchase-orders.restore');
        Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::apiResource('purchase-receipts', PurchaseReceiptController::class)->only(['index', 'show', 'destroy']);

        // Financial core
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);
        Route::post('chart-of-accounts/{chart_of_account}/restore', [ChartOfAccountController::class, 'restore'])->name('chart-of-accounts.restore');

        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'postEntry'])->name('journal-entries.post');
        Route::post('journal-entries/{journal_entry}/void', [JournalEntryController::class, 'void'])->name('journal-entries.void');

        Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');

        Route::apiResource('payments', PaymentController::class);
        Route::post('payments/{payment}/restore', [PaymentController::class, 'restore'])->name('payments.restore');
        Route::post('payments/{payment}/complete', [PaymentController::class, 'complete'])->name('payments.complete');

        // Point of sale: sellable tills and the orders they ring up. The
        // summary feed is registered before the resource so `summary` is not
        // swallowed by the {pos_order} show route.
        Route::apiResource('pos-registers', PosRegisterController::class);
        Route::post('pos-registers/{pos_register}/restore', [PosRegisterController::class, 'restore'])
            ->name('pos-registers.restore');
        Route::get('pos-orders/summary', [PosOrderController::class, 'summary'])
            ->name('pos-orders.summary');
        Route::apiResource('pos-orders', PosOrderController::class);
        Route::post('pos-orders/{pos_order}/restore', [PosOrderController::class, 'restore'])
            ->name('pos-orders.restore');

        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::post('bank-accounts/{bank_account}/restore', [BankAccountController::class, 'restore'])->name('bank-accounts.restore');
        Route::apiResource('bank-transactions', BankTransactionController::class);
        Route::post('bank-transactions/{bank_transaction}/restore', [BankTransactionController::class, 'restore'])->name('bank-transactions.restore');
        Route::post('bank-transactions/import', [BankTransactionController::class, 'import'])->name('bank-transactions.import');
        Route::post('bank-transactions/{bank_transaction}/match', [BankTransactionController::class, 'matchTransaction'])->name('bank-transactions.match');
        Route::post('bank-transactions/{bank_transaction}/unmatch', [BankTransactionController::class, 'unmatch'])->name('bank-transactions.unmatch');

        Route::apiResource('reconciliations', ReconciliationController::class);
        Route::post('reconciliations/{reconciliation}/restore', [ReconciliationController::class, 'restore'])->name('reconciliations.restore');
        Route::post('reconciliations/{reconciliation}/complete', [ReconciliationController::class, 'complete'])->name('reconciliations.complete');

        // In-app notifications (company-scoped inbox, bell badge support)
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::apiResource('notifications', NotificationController::class);

        // Audit trail
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{audit_log}', [AuditLogController::class, 'show'])->name('audit-logs.show');

        // Reports & dashboard
        Route::get('reports/profit-and-loss', [FinancialReportController::class, 'profitAndLoss'])->name('reports.profit-and-loss');
        Route::get('reports/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.cash-flow');
        Route::get('reports/aging', [FinancialReportController::class, 'aging'])->name('reports.aging');
        Route::get('reports/sales-by-product', [FinancialReportController::class, 'salesByProduct'])->name('reports.sales-by-product');
        Route::get('reports/sales-by-category', [FinancialReportController::class, 'salesByCategory'])->name('reports.sales-by-category');
        Route::get('reports/vat-summary', [FinancialReportController::class, 'vatSummary'])->name('reports.vat-summary');

        Route::get('dashboard', [DashboardController::class, 'summary'])->name('dashboard.summary');
        // Dashboard card feeds. Each one is limit-capped on its own so a
        // heavy table cannot slow down the KPI summary above.
        Route::get('dashboard/financial-year-sales', [DashboardController::class, 'financialYearSales'])
            ->name('dashboard.financial-year-sales');
        Route::get('dashboard/sales-payment-due', [DashboardController::class, 'salesPaymentDue'])
            ->name('dashboard.sales-payment-due');
        Route::get('dashboard/purchase-payment-due', [DashboardController::class, 'purchasePaymentDue'])
            ->name('dashboard.purchase-payment-due');
        Route::get('dashboard/stock-alert', [DashboardController::class, 'stockAlert'])
            ->name('dashboard.stock-alert');
        Route::get('dashboard/sales-orders', [DashboardController::class, 'salesOrders'])
            ->name('dashboard.sales-orders');
        Route::get('dashboard/pending-shipments', [DashboardController::class, 'pendingShipments'])
            ->name('dashboard.pending-shipments');
        Route::get('dashboard/crm-summary', [DashboardController::class, 'crmSummary'])
            ->name('dashboard.crm-summary');
        Route::get('dashboard/project-summary', [DashboardController::class, 'projectSummary'])
            ->name('dashboard.project-summary');
        Route::get('dashboard/pos-summary', [DashboardController::class, 'posSummary'])
            ->name('dashboard.pos-summary');
        Route::get('currencies', [CurrenciesController::class, 'index'])->name('currencies.index');
        Route::apiResource('currency-rates', CurrencyRateController::class);
        Route::post('currency-rates/{currency_rate}/restore', [CurrencyRateController::class, 'restore'])->name('currency-rates.restore');

        // Settings (mirrors WorkDo Dash settings surface)
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('settings/{group}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/cache/clear', [SettingsController::class, 'clearCache'])->name('settings.cache-clear');
        Route::post('settings/email/test', [SettingsController::class, 'sendTestEmail'])->name('settings.email-test');

        // Expenses & Income
        Route::apiResource('expense-categories', ExpenseCategoryController::class);
        Route::post('expense-categories/{expense_category}/restore', [ExpenseCategoryController::class, 'restore'])->name('expense-categories.restore');
        Route::apiResource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])->name('expenses.restore');
        Route::apiResource('income-categories', IncomeCategoryController::class);
        Route::post('income-categories/{income_category}/restore', [IncomeCategoryController::class, 'restore'])->name('income-categories.restore');
        Route::apiResource('incomes', IncomeController::class);
        Route::post('incomes/{income}/restore', [IncomeController::class, 'restore'])->name('incomes.restore');

        // Stock management
        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/{stock_adjustment}/restore', [StockAdjustmentController::class, 'restore'])->name('stock-adjustments.restore');
        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{stock_transfer}/restore', [StockTransferController::class, 'restore'])->name('stock-transfers.restore');
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete'])->name('stock-transfers.complete');
        Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');

        // Quotations
        Route::apiResource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/restore', [QuotationController::class, 'restore'])->name('quotations.restore');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');

        // CRM: pipeline stages first, then the leads and deals that sit on
        // them. `convert` promotes a lead into a contact plus an open deal.
        Route::apiResource('crm-stages', CrmStageController::class);
        Route::apiResource('leads', LeadController::class);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::apiResource('deals', DealController::class);

        // Projects: delivery tracking with the tasks and milestones that hang
        // off each project. The summary feed is registered before the resource
        // so `summary` is not swallowed by the {project} show route.
        Route::get('projects/summary', [ProjectController::class, 'summary'])
            ->name('projects.summary');
        Route::apiResource('projects', ProjectController::class);
        Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])
            ->name('projects.restore');
        Route::apiResource('project-tasks', ProjectTaskController::class);
        Route::post('project-tasks/{project_task}/restore', [ProjectTaskController::class, 'restore'])
            ->name('project-tasks.restore');
        Route::apiResource('project-milestones', ProjectMilestoneController::class);
        Route::post('project-milestones/{project_milestone}/restore', [ProjectMilestoneController::class, 'restore'])
            ->name('project-milestones.restore');

        // HRM: org chart first (departments, designations), then the people
        // on it, the days they work and the days they are away. The summary
        // feed is registered before the employee resource so `summary` is not
        // swallowed by the {hrm_employee} show route.
        Route::get('hrm-employees/summary', [HrmEmployeeController::class, 'summary'])
            ->name('hrm-employees.summary');
        Route::apiResource('hrm-departments', HrmDepartmentController::class);
        Route::post('hrm-departments/{hrm_department}/restore', [HrmDepartmentController::class, 'restore'])
            ->name('hrm-departments.restore');
        Route::apiResource('hrm-designations', HrmDesignationController::class);
        Route::post('hrm-designations/{hrm_designation}/restore', [HrmDesignationController::class, 'restore'])
            ->name('hrm-designations.restore');
        Route::apiResource('hrm-employees', HrmEmployeeController::class);
        Route::post('hrm-employees/{hrm_employee}/restore', [HrmEmployeeController::class, 'restore'])
            ->name('hrm-employees.restore');
        Route::apiResource('hrm-attendance', HrmAttendanceController::class);
        Route::apiResource('hrm-leaves', HrmLeaveController::class);
        Route::post('hrm-leaves/{hrm_leave}/restore', [HrmLeaveController::class, 'restore'])
            ->name('hrm-leaves.restore');
        Route::post('hrm-leaves/{hrm_leave}/approve', [HrmLeaveController::class, 'approve'])
            ->name('hrm-leaves.approve');
        Route::post('hrm-leaves/{hrm_leave}/reject', [HrmLeaveController::class, 'reject'])
            ->name('hrm-leaves.reject');

        // Business settings (mirrors UltimatePOS Business Settings surface)
        Route::get('business-settings', [BusinessSettingsController::class, 'index'])->name('business-settings.index');
        Route::get('business-settings/{group}', [BusinessSettingsController::class, 'show'])->name('business-settings.show');
        Route::put('business-settings/{group}', [BusinessSettingsController::class, 'update'])->name('business-settings.update');

        // Business locations (mirrors UltimatePOS Business Locations settings surface)
        Route::apiResource('business-locations', BusinessLocationController::class);
        Route::post('business-locations/{business_location}/restore', [BusinessLocationController::class, 'restore'])->name('business-locations.restore');

        // User management (mirrors UltimatePOS Manage Users / Manage Roles surface)
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);

        // Outbound webhooks
        Route::get('webhook-events', [WebhookEndpointController::class, 'events'])->name('webhook-events.index');
        Route::apiResource('webhook-endpoints', WebhookEndpointController::class);
        Route::get('webhook-endpoints/{webhook_endpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhook-endpoints.deliveries');
        Route::post('webhook-endpoints/{webhook_endpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhook-endpoints.test');
        Route::post('webhook-endpoints/{webhook_endpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhook-endpoints.rotate-secret');
        Route::get('webhook-deliveries', [WebhookDeliveryController::class, 'index'])->name('webhook-deliveries.index');
        Route::get('webhook-deliveries/{webhook_delivery}', [WebhookDeliveryController::class, 'show'])->name('webhook-deliveries.show');
        Route::post('webhook-deliveries/{webhook_delivery}/redeliver', [WebhookDeliveryController::class, 'redeliver'])->name('webhook-deliveries.redeliver');

        // Add-on catalog. Readable by every staff member (the SPA needs it to
        // decide what to render); the toggle write rides addons.update.
        Route::get('addons', [AddonController::class, 'index'])->name('addons.index');
        Route::put('addons/{addon}', [AddonController::class, 'toggle'])->name('addons.toggle');

        // ---- Add-on modules. Each group is additionally gated by its own
        // toggle (EnsureAddon), so a switched-off add-on answers 403 rather
        // than exposing routes the SPA no longer renders.
        Route::middleware('addon:notice-board')->group(function () {
            Route::apiResource('notices', NoticeController::class);
            Route::post('notices/{notice}/restore', [NoticeController::class, 'restore'])->name('notices.restore');
        });

        Route::middleware('addon:suggestion-box')->group(function () {
            Route::apiResource('suggestions', SuggestionController::class);
            Route::post('suggestions/{suggestion}/restore', [SuggestionController::class, 'restore'])->name('suggestions.restore');
        });

        Route::middleware('addon:business-advisor')->group(function () {
            Route::get('business-advisor/report', [BusinessAdvisorController::class, 'index'])
                ->name('business-advisor.index');
            Route::get('business-advisor/snapshots', [BusinessAdvisorController::class, 'snapshots'])
                ->name('business-advisor.snapshots');
        });

        // Smart Dashboard. Four read-only tab payloads sharing one
        // permission (analytics.view), all cached inside the service.
        Route::middleware('addon:smart-dashboard')->group(function () {
            Route::get('analytics/executive', [AnalyticsController::class, 'executive'])
                ->name('analytics.executive');
            Route::get('analytics/financial', [AnalyticsController::class, 'financial'])
                ->name('analytics.financial');
            Route::get('analytics/sales', [AnalyticsController::class, 'sales'])
                ->name('analytics.sales');
            Route::get('analytics/operations', [AnalyticsController::class, 'operations'])
                ->name('analytics.operations');
        });

        // Coworking Space: reusable units first (desks, offices, rooms), then
        // the bookings on them. The availability feed is registered before the
        // unit resource so `availability` is not swallowed by the {coworking_unit}
        // show route, and the booking summary behaves the same way.
        Route::middleware('addon:coworking-space')->group(function () {
            Route::get('coworking-units/availability', [CoworkingUnitController::class, 'availability'])
                ->name('coworking-units.availability');
            Route::apiResource('coworking-units', CoworkingUnitController::class);
            Route::post('coworking-units/{coworking_unit}/restore', [CoworkingUnitController::class, 'restore'])
                ->name('coworking-units.restore');

            Route::get('coworking-bookings/summary', [CoworkingBookingController::class, 'summary'])
                ->name('coworking-bookings.summary');
            Route::apiResource('coworking-bookings', CoworkingBookingController::class);
            Route::post('coworking-bookings/{coworking_booking}/restore', [CoworkingBookingController::class, 'restore'])
                ->name('coworking-bookings.restore');
            Route::post('coworking-bookings/{coworking_booking}/confirm', [CoworkingBookingController::class, 'confirm'])
                ->name('coworking-bookings.confirm');
            Route::post('coworking-bookings/{coworking_booking}/check-in', [CoworkingBookingController::class, 'checkIn'])
                ->name('coworking-bookings.check-in');
            Route::post('coworking-bookings/{coworking_booking}/check-out', [CoworkingBookingController::class, 'checkOut'])
                ->name('coworking-bookings.check-out');
            Route::post('coworking-bookings/{coworking_booking}/cancel', [CoworkingBookingController::class, 'cancel'])
                ->name('coworking-bookings.cancel');
        });

        // Laundry & Dry Cleaning: a linear garment flow on the order, with the
        // priced line items hanging off it. The summary feed is registered
        // before the resource so `summary` is not read as an order id.
        Route::middleware('addon:laundry-dry-cleaning')->group(function () {
            Route::get('laundry-orders/summary', [LaundryOrderController::class, 'summary'])
                ->name('laundry-orders.summary');
            Route::apiResource('laundry-orders', LaundryOrderController::class);
            Route::post('laundry-orders/{laundry_order}/restore', [LaundryOrderController::class, 'restore'])
                ->name('laundry-orders.restore');
            Route::post('laundry-orders/{laundry_order}/advance', [LaundryOrderController::class, 'advance'])
                ->name('laundry-orders.advance');
            Route::post('laundry-orders/{laundry_order}/cancel', [LaundryOrderController::class, 'cancel'])
                ->name('laundry-orders.cancel');

            Route::apiResource('laundry-items', LaundryItemController::class);
            Route::post('laundry-items/{laundry_item}/restore', [LaundryItemController::class, 'restore'])
                ->name('laundry-items.restore');
        });

        // Gym Management: membership plans, the members on them, and the visits
        // they clock. Check-in is the visit store action; the member summary
        // feed is registered before the resource so `summary` is not read as an
        // id, and suspend/activate carry their own routes.
        Route::middleware('addon:gym-management')->group(function () {
            Route::apiResource('gym-plans', GymPlanController::class);
            Route::post('gym-plans/{gym_plan}/restore', [GymPlanController::class, 'restore'])
                ->name('gym-plans.restore');

            Route::get('gym-members/summary', [GymMemberController::class, 'summary'])
                ->name('gym-members.summary');
            Route::apiResource('gym-members', GymMemberController::class);
            Route::post('gym-members/{gym_member}/restore', [GymMemberController::class, 'restore'])
                ->name('gym-members.restore');
            Route::post('gym-members/{gym_member}/suspend', [GymMemberController::class, 'suspend'])
                ->name('gym-members.suspend');
            Route::post('gym-members/{gym_member}/activate', [GymMemberController::class, 'activate'])
                ->name('gym-members.activate');

            Route::apiResource('gym-visits', GymVisitController::class);
            Route::post('gym-visits/{gym_visit}/check-out', [GymVisitController::class, 'checkOut'])
                ->name('gym-visits.check-out');
            Route::post('gym-visits/{gym_visit}/restore', [GymVisitController::class, 'restore'])
                ->name('gym-visits.restore');
        });

        // Rental Management: itemised agreements that move from draft to
        // active, then close as line items come back. The summary feed and the
        // per-item return action carry their own routes.
        Route::middleware('addon:rental')->group(function () {
            Route::get('rental-agreements/summary', [RentalAgreementController::class, 'summary'])
                ->name('rental-agreements.summary');
            Route::apiResource('rental-agreements', RentalAgreementController::class);
            Route::post('rental-agreements/{rental_agreement}/restore', [RentalAgreementController::class, 'restore'])
                ->name('rental-agreements.restore');
            Route::post('rental-agreements/{rental_agreement}/activate', [RentalAgreementController::class, 'activate'])
                ->name('rental-agreements.activate');
            Route::post('rental-agreements/{rental_agreement}/cancel', [RentalAgreementController::class, 'cancel'])
                ->name('rental-agreements.cancel');

            Route::apiResource('rental-items', RentalItemController::class);
            Route::post('rental-items/{rental_item}/return', [RentalItemController::class, 'returnItem'])
                ->name('rental-items.return');
            Route::post('rental-items/{rental_item}/restore', [RentalItemController::class, 'restore'])
                ->name('rental-items.restore');
        });

        // ---- Cross-domain tools. Same add-on contract as the verticals, but
        // useful to any company. Read feeds (summary) are registered before
        // their resource so the literal path is never read as an id.
        Route::middleware('addon:form-builder')->group(function () {
            Route::apiResource('forms', FormController::class);
            Route::post('forms/{form}/restore', [FormController::class, 'restore'])->name('forms.restore');
            Route::post('forms/{form}/publish', [FormController::class, 'publish'])->name('forms.publish');
            Route::post('forms/{form}/archive', [FormController::class, 'archive'])->name('forms.archive');

            Route::apiResource('form-fields', FormFieldController::class);
            Route::post('form-fields/{form_field}/restore', [FormFieldController::class, 'restore'])
                ->name('form-fields.restore');

            Route::apiResource('form-submissions', FormSubmissionController::class);
            Route::post('form-submissions/{form_submission}/restore', [FormSubmissionController::class, 'restore'])
                ->name('form-submissions.restore');
        });

        Route::middleware('addon:messenger')->group(function () {
            Route::apiResource('conversations', ConversationController::class);
            Route::post('conversations/{conversation}/restore', [ConversationController::class, 'restore'])
                ->name('conversations.restore');
            Route::post('conversations/{conversation}/close', [ConversationController::class, 'close'])
                ->name('conversations.close');
            Route::post('conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])
                ->name('conversations.reopen');

            Route::apiResource('messages', MessageController::class);
            Route::post('messages/{message}/restore', [MessageController::class, 'restore'])
                ->name('messages.restore');
        });

        Route::middleware('addon:document-manager')->group(function () {
            Route::apiResource('document-folders', DocumentFolderController::class);
            Route::post('document-folders/{document_folder}/restore', [DocumentFolderController::class, 'restore'])
                ->name('document-folders.restore');

            Route::apiResource('documents', DocumentController::class);
            Route::post('documents/{document}/restore', [DocumentController::class, 'restore'])
                ->name('documents.restore');
            Route::post('documents/{document}/share', [DocumentController::class, 'share'])
                ->name('documents.share');
        });

        Route::middleware('addon:support-tickets')->group(function () {
            Route::apiResource('tickets', TicketController::class);
            Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->name('tickets.restore');
            Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
            Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
            Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
            Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');

            Route::apiResource('ticket-replies', TicketReplyController::class);
            Route::post('ticket-replies/{ticket_reply}/restore', [TicketReplyController::class, 'restore'])
                ->name('ticket-replies.restore');
        });

        Route::middleware('addon:calendar-events')->group(function () {
            Route::apiResource('events', EventController::class);
            Route::post('events/{event}/restore', [EventController::class, 'restore'])->name('events.restore');
            Route::post('events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');

            Route::apiResource('event-attendees', EventAttendeeController::class);
            Route::post('event-attendees/{event_attendee}/restore', [EventAttendeeController::class, 'restore'])
                ->name('event-attendees.restore');
            Route::post('event-attendees/{event_attendee}/respond', [EventAttendeeController::class, 'respond'])
                ->name('event-attendees.respond');
        });

        Route::middleware('addon:asset-management')->group(function () {
            Route::apiResource('asset-categories', AssetCategoryController::class);
            Route::post('asset-categories/{asset_category}/restore', [AssetCategoryController::class, 'restore'])
                ->name('asset-categories.restore');

            Route::apiResource('assets', AssetController::class);
            Route::post('assets/{asset}/restore', [AssetController::class, 'restore'])->name('assets.restore');
            Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->name('assets.assign');
            Route::post('assets/{asset}/return', [AssetController::class, 'returnAsset'])->name('assets.return');
            Route::post('assets/{asset}/retire', [AssetController::class, 'retire'])->name('assets.retire');

            Route::apiResource('asset-assignments', AssetAssignmentController::class);
            Route::post('asset-assignments/{asset_assignment}/restore', [AssetAssignmentController::class, 'restore'])
                ->name('asset-assignments.restore');
        });

        // ---- Phase 1: analytics + tools.
        Route::middleware('addon:smart-reports')->group(function () {
            Route::apiResource('smart-reports', SmartReportController::class);
            Route::post('smart-reports/{smart_report}/restore', [SmartReportController::class, 'restore'])
                ->name('smart-reports.restore');
            Route::post('smart-reports/{smart_report}/run', [SmartReportController::class, 'run'])
                ->name('smart-reports.run');
        });

        Route::middleware('addon:ai-agent')->group(function () {
            Route::apiResource('ai-agent-threads', AiAgentThreadController::class);
            Route::post('ai-agent-threads/{ai_agent_thread}/restore', [AiAgentThreadController::class, 'restore'])
                ->name('ai-agent-threads.restore');

            Route::get('ai-agent-threads/{ai_agent_thread}/messages', [AiAgentMessageController::class, 'index'])
                ->name('ai-agent-messages.index');
            Route::post('ai-agent-threads/{ai_agent_thread}/messages', [AiAgentMessageController::class, 'store'])
                ->name('ai-agent-messages.store');
            Route::apiResource('ai-agent-messages', AiAgentMessageController::class)->except(['index', 'store']);
            Route::post('ai-agent-messages/{ai_agent_message}/restore', [AiAgentMessageController::class, 'restore'])
                ->name('ai-agent-messages.restore');

            Route::apiResource('ai-agent-prompts', AiAgentPromptController::class);
            Route::post('ai-agent-prompts/{ai_agent_prompt}/restore', [AiAgentPromptController::class, 'restore'])
                ->name('ai-agent-prompts.restore');
        });

        Route::middleware('addon:goals')->group(function () {
            Route::apiResource('goals', GoalController::class);
            Route::post('goals/{goal}/restore', [GoalController::class, 'restore'])->name('goals.restore');
            Route::post('goals/{goal}/progress', [GoalController::class, 'progress'])->name('goals.progress');
            Route::post('goals/{goal}/cancel', [GoalController::class, 'cancel'])->name('goals.cancel');

            Route::apiResource('goal-key-results', GoalKeyResultController::class);
            Route::post('goal-key-results/{goal_key_result}/restore', [GoalKeyResultController::class, 'restore'])
                ->name('goal-key-results.restore');
        });

        Route::middleware('addon:budget-planner')->group(function () {
            Route::apiResource('budgets', BudgetController::class);
            Route::post('budgets/{budget}/restore', [BudgetController::class, 'restore'])->name('budgets.restore');
            Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve');
            Route::post('budgets/{budget}/close', [BudgetController::class, 'close'])->name('budgets.close');

            Route::apiResource('budget-lines', BudgetLineController::class);
            Route::post('budget-lines/{budget_line}/restore', [BudgetLineController::class, 'restore'])
                ->name('budget-lines.restore');
        });

        Route::middleware('addon:training')->group(function () {
            Route::apiResource('trainings', TrainingController::class);
            Route::post('trainings/{training}/restore', [TrainingController::class, 'restore'])->name('trainings.restore');
            Route::post('trainings/{training}/cancel', [TrainingController::class, 'cancel'])->name('trainings.cancel');

            Route::apiResource('training-types', TrainingTypeController::class);
            Route::post('training-types/{training_type}/restore', [TrainingTypeController::class, 'restore'])
                ->name('training-types.restore');
        });

        Route::middleware('addon:youtube')->group(function () {
            Route::apiResource('youtube-videos', YoutubeVideoController::class);
            Route::post('youtube-videos/{youtube_video}/restore', [YoutubeVideoController::class, 'restore'])
                ->name('youtube-videos.restore');

            Route::apiResource('youtube-playlists', YoutubePlaylistController::class);
            Route::post('youtube-playlists/{youtube_playlist}/restore', [YoutubePlaylistController::class, 'restore'])
                ->name('youtube-playlists.restore');

            Route::apiResource('youtube-channel', YoutubeChannelController::class);
            Route::post('youtube-channel/{youtube_channel}/restore', [YoutubeChannelController::class, 'restore'])
                ->name('youtube-channel.restore');

            Route::apiResource('youtube-activity', YoutubeActivityController::class);
            Route::post('youtube-activity/{youtube_activity}/restore', [YoutubeActivityController::class, 'restore'])
                ->name('youtube-activity.restore');
        });
    });
});
