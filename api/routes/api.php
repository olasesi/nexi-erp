<?php

use App\Http\Controllers\Api\ApiVersionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankTransactionController;
use App\Http\Controllers\Api\V1\BusinessLocationController;
use App\Http\Controllers\Api\V1\BusinessSettingsController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CurrenciesController;
use App\Http\Controllers\Api\V1\CurrencyRateController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\IncomeCategoryController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PublicPaymentController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseReceiptController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WebhookDeliveryController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
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
    });
});
