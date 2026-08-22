<?php

use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CapitalController;
use App\Http\Controllers\CoaController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OntController;
use App\Http\Controllers\OtherIncomeController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Customers (Network Admin & Owner, Finance can view)
    Route::resource('customers', CustomerController::class)->except(['destroy']);
    Route::post('/customers/{customer}/terminate', [CustomerController::class, 'terminate'])->name('customers.terminate');
    Route::post('/customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('customers.reactivate');

    // Internet Packages
    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

    // Promotions
    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::post('/promotions/assign', [PromotionController::class, 'assign'])->name('promotions.assign');
    Route::post('/promotions/{customerPromotion}/cancel', [PromotionController::class, 'cancelAssignment'])->name('promotions.cancel');

    // ONT Inventory
    Route::get('/ont', [OntController::class, 'index'])->name('ont.index');
    Route::post('/ont', [OntController::class, 'store'])->name('ont.store');
    Route::get('/ont/{ont}', [OntController::class, 'show'])->name('ont.show');
    Route::post('/ont/{ont}/assign', [OntController::class, 'assign'])->name('ont.assign');
    Route::post('/ont/{ont}/return', [OntController::class, 'returnOnt'])->name('ont.return');

    // Billing & Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');

    // Payments & MDR
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments/preview', [PaymentController::class, 'preview'])->name('payments.preview');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/{payment}/reversal', [PaymentController::class, 'requestReversal'])->name('payments.reversal');

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Other Income & Capital
    Route::get('/other-income', [OtherIncomeController::class, 'index'])->name('other-income.index');
    Route::post('/other-income', [OtherIncomeController::class, 'store'])->name('other-income.store');
    Route::get('/capital', [CapitalController::class, 'index'])->name('capital.index');
    Route::post('/capital', [CapitalController::class, 'store'])->name('capital.store');

    // Accounting & COA
    Route::get('/accounting/coa', [CoaController::class, 'index'])->name('accounting.coa');
    Route::post('/accounting/coa', [CoaController::class, 'store'])->name('accounting.coa.store');
    Route::get('/accounting/opening-balance', [CoaController::class, 'openingBalance'])->name('accounting.opening-balance');
    Route::post('/accounting/opening-balance', [CoaController::class, 'postOpeningBalance'])->name('accounting.opening-balance.store');
    Route::get('/accounting/journals', [JournalController::class, 'index'])->name('accounting.journals');
    Route::post('/accounting/journals/manual', [JournalController::class, 'storeManual'])->name('accounting.journals.manual');
    Route::get('/accounting/ledger', [JournalController::class, 'ledger'])->name('accounting.ledger');
    Route::get('/accounting/trial-balance', [JournalController::class, 'trialBalance'])->name('accounting.trial-balance');
    Route::get('/accounting/periods', [AccountingPeriodController::class, 'index'])->name('accounting.periods');

    // Financial Reports
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/equity-changes', [ReportController::class, 'equityChanges'])->name('reports.equity-changes');
    Route::get('/reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');

    // Network & MikroTik
    Route::get('/mikrotik', [MikrotikController::class, 'index'])->name('mikrotik.index');
    Route::post('/mikrotik/router', [MikrotikController::class, 'updateRouter'])->name('mikrotik.router.update');
    Route::post('/mikrotik/test', [MikrotikController::class, 'testConnection'])->name('mikrotik.test');
    Route::get('/mikrotik/resource', [MikrotikController::class, 'resource'])->name('mikrotik.resource');
    Route::post('/mikrotik/process-jobs', [MikrotikController::class, 'processJobs'])->name('mikrotik.process-jobs');
    Route::post('/mikrotik/toggle-isolate/{customer}', [MikrotikController::class, 'toggleIsolate'])->name('mikrotik.toggle-isolate');

    // Owner Only Routes
    Route::middleware('role:owner')->group(function () {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
        Route::post('/approvals/reversal/{reversalRequest}/approve', [ApprovalController::class, 'approveReversal'])->name('approvals.reversal.approve');
        Route::post('/approvals/reversal/{reversalRequest}/reject', [ApprovalController::class, 'rejectReversal'])->name('approvals.reversal.reject');

        Route::post('/accounting/periods', [AccountingPeriodController::class, 'store'])->name('accounting.periods.store');
        Route::post('/accounting/periods/close', [AccountingPeriodController::class, 'close'])->name('accounting.periods.close');
        Route::post('/accounting/periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopen'])->name('accounting.periods.reopen');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });
});
