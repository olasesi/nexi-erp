<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankTransactionController;
use App\Http\Controllers\Api\V1\BusinessLocationController;
use App\Http\Controllers\Api\V1\BusinessSettingsController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CurrenciesController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\IncomeCategoryController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/metrics', MetricsController::class);

Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('guest');

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('guest');

    Route::post('/customer/register', [CustomerPortalController::class, 'register'])
        ->middleware('guest');

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
        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('sales-orders', SalesOrderController::class);
        Route::apiResource('purchase-orders', PurchaseOrderController::class);

        // Financial core
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);

        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'postEntry']);
        Route::post('journal-entries/{journal_entry}/void', [JournalEntryController::class, 'void']);

        Route::apiResource('invoices', InvoiceController::class);

        Route::apiResource('payments', PaymentController::class);
        Route::post('payments/{payment}/complete', [PaymentController::class, 'complete']);

        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::apiResource('bank-transactions', BankTransactionController::class);
        Route::post('bank-transactions/import', [BankTransactionController::class, 'import']);
        Route::post('bank-transactions/{bank_transaction}/match', [BankTransactionController::class, 'matchTransaction']);
        Route::post('bank-transactions/{bank_transaction}/unmatch', [BankTransactionController::class, 'unmatch']);

        Route::apiResource('reconciliations', ReconciliationController::class);
        Route::post('reconciliations/{reconciliation}/complete', [ReconciliationController::class, 'complete']);

        // Reports & dashboard
        Route::get('reports/profit-and-loss', [FinancialReportController::class, 'profitAndLoss']);
        Route::get('reports/balance-sheet', [FinancialReportController::class, 'balanceSheet']);
        Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow']);
        Route::get('reports/aging', [FinancialReportController::class, 'aging']);

        Route::get('dashboard', [DashboardController::class, 'summary']);
        Route::get('currencies', [CurrenciesController::class, 'index']);

        // Settings (mirrors WorkDo Dash settings surface)
        Route::get('settings', [SettingsController::class, 'index']);
        Route::get('settings/{group}', [SettingsController::class, 'show']);
        Route::put('settings/{group}', [SettingsController::class, 'update']);
        Route::post('settings/cache/clear', [SettingsController::class, 'clearCache']);
        Route::post('settings/email/test', [SettingsController::class, 'sendTestEmail']);

        // Expenses & Income
        Route::apiResource('expense-categories', ExpenseCategoryController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('income-categories', IncomeCategoryController::class);
        Route::apiResource('incomes', IncomeController::class);

        // Stock management
        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete']);
        Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel']);

        // Quotations
        Route::apiResource('quotations', QuotationController::class);
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept']);
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject']);

        // Business settings (mirrors UltimatePOS Business Settings surface)
        Route::get('business-settings', [BusinessSettingsController::class, 'index']);
        Route::get('business-settings/{group}', [BusinessSettingsController::class, 'show']);
        Route::put('business-settings/{group}', [BusinessSettingsController::class, 'update']);

        // Business locations (mirrors UltimatePOS Business Locations settings surface)
        Route::apiResource('business-locations', BusinessLocationController::class);
    });
});
