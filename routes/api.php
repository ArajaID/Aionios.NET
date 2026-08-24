<?php

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\NetworkController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OntController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ApiResponse::success(['status' => 'ok']));
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

    Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/mobile/dashboard', DashboardController::class)->middleware('api.permission:dashboard.view');

        // Customer lifecycle & profile
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('api.permission:customers.view');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('api.permission:customers.view');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware(['api.permission:customers.manage', 'throttle:api-write']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware(['api.permission:customers.manage', 'throttle:api-write']);
        Route::post('/customers/{customer}/change-package', [CustomerController::class, 'changePackage'])->middleware(['api.permission:customers.manage', 'throttle:api-write']);
        Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])->middleware(['api.permission:customers.manage', 'throttle:api-write', 'api.idempotent']);
        Route::post('/customers/{customer}/terminate', [CustomerController::class, 'terminate'])->middleware(['api.permission:customers.manage', 'throttle:api-write', 'api.idempotent']);
        Route::post('/customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->middleware(['api.permission:customers.manage', 'throttle:api-write', 'api.idempotent']);

        // ONT Inventory
        Route::get('/onts', [OntController::class, 'index'])->middleware('api.permission:onts.view');
        Route::get('/onts/suggested-id', [OntController::class, 'suggestedId'])->middleware('api.permission:onts.view');
        Route::post('/onts', [OntController::class, 'store'])->middleware(['api.permission:onts.manage', 'throttle:api-write']);
        Route::get('/onts/{ont}', [OntController::class, 'show'])->middleware('api.permission:onts.view');
        Route::get('/onts/{ont}/history', [OntController::class, 'history'])->middleware('api.permission:onts.view');
        Route::post('/customers/{customer}/ont/assign', [OntController::class, 'assign'])->middleware(['api.permission:onts.manage', 'throttle:api-write', 'api.idempotent']);
        Route::post('/customers/{customer}/ont/return', [OntController::class, 'return'])->middleware(['api.permission:onts.manage', 'throttle:api-write', 'api.idempotent']);

        // Network Provisioning & Isolation
        Route::get('/network/status', [NetworkController::class, 'status'])->middleware('api.permission:network.view');
        Route::get('/network/jobs', [NetworkController::class, 'jobs'])->middleware('api.permission:network.view');
        Route::get('/network/jobs/{job}', [NetworkController::class, 'job'])->middleware('api.permission:network.view');
        Route::post('/network/jobs/{job}/retry', [NetworkController::class, 'retry'])->middleware(['api.permission:network.retry', 'throttle:api-write', 'api.idempotent']);
        Route::get('/customers/{customer}/network', [NetworkController::class, 'customer'])->middleware('api.permission:network.view');
        Route::post('/customers/{customer}/network/sync', [NetworkController::class, 'sync'])->middleware(['api.permission:network.manage', 'throttle:api-write', 'api.idempotent']);
        Route::post('/customers/{customer}/network/isolate', [NetworkController::class, 'isolate'])->middleware(['api.permission:network.manage', 'throttle:api-write', 'api.idempotent']);
        Route::post('/customers/{customer}/network/unisolate', [NetworkController::class, 'unisolate'])->middleware(['api.permission:network.manage', 'throttle:api-write', 'api.idempotent']);

        // Billing & Invoices
        Route::get('/customers/{customer}/invoices', [BillingController::class, 'customerInvoices'])->middleware('api.permission:billing.view');
        Route::get('/customers/{customer}/outstanding', [BillingController::class, 'outstanding'])->middleware('api.permission:billing.view');
        Route::get('/invoices/{invoice}', [BillingController::class, 'invoice'])->middleware('api.permission:billing.view');
        Route::post('/invoices/{invoice}/adjust', [BillingController::class, 'adjust'])->middleware(['api.permission:billing.manage', 'throttle:api-write']);

        // Owner Approvals Hub
        Route::prefix('approvals')->group(function () {
            Route::get('/summary', [ApprovalController::class, 'summary'])->middleware('api.permission:approvals.view');
            Route::get('/invoice-adjustments', [ApprovalController::class, 'invoiceAdjustments'])->middleware('api.permission:approvals.view');
            Route::post('/invoice-adjustments/{adjRequest}/approve', [ApprovalController::class, 'approveInvoiceAdjustment'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
            Route::post('/invoice-adjustments/{adjRequest}/reject', [ApprovalController::class, 'rejectInvoiceAdjustment'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
            Route::get('/package-changes', [ApprovalController::class, 'packageChanges'])->middleware('api.permission:approvals.view');
            Route::post('/package-changes/{pkgRequest}/approve', [ApprovalController::class, 'approvePackageChange'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
            Route::post('/package-changes/{pkgRequest}/reject', [ApprovalController::class, 'rejectPackageChange'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
            Route::get('/reversals', [ApprovalController::class, 'reversals'])->middleware('api.permission:approvals.view');
            Route::post('/reversals/{revRequest}/approve', [ApprovalController::class, 'approveReversal'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
            Route::post('/reversals/{revRequest}/reject', [ApprovalController::class, 'rejectReversal'])->middleware(['api.permission:approvals.manage', 'throttle:api-write']);
        });

        // Payments & Reversal Requests
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('api.permission:payments.view');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('api.permission:payments.view');
        Route::post('/payments/preview', [PaymentController::class, 'preview'])->middleware(['api.permission:payments.create', 'throttle:api-write']);
        Route::post('/payments', [PaymentController::class, 'store'])->middleware(['api.permission:payments.create', 'throttle:api-write', 'api.idempotent']);
        Route::post('/payments/{payment}/reversal-request', [PaymentController::class, 'requestReversal'])->middleware(['api.permission:payments.reversal', 'throttle:api-write', 'api.idempotent']);

        // Incomes
        Route::get('/incomes', [IncomeController::class, 'index'])->middleware('api.permission:incomes.view');
        Route::get('/incomes/{income}', [IncomeController::class, 'show'])->middleware('api.permission:incomes.view');
        Route::post('/incomes/preview', [IncomeController::class, 'preview'])->middleware(['api.permission:incomes.create', 'throttle:api-write']);
        Route::post('/incomes', [IncomeController::class, 'store'])->middleware(['api.permission:incomes.create', 'throttle:api-write', 'api.idempotent']);

        // Expenses
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('api.permission:expenses.view');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->middleware('api.permission:expenses.view');
        Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])->middleware('api.permission:expenses.view');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware(['api.permission:expenses.create', 'throttle:api-write']);
        Route::post('/expenses/{expense}/submit', [ExpenseController::class, 'submit'])->middleware(['api.permission:expenses.create', 'throttle:api-write', 'api.idempotent']);
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->middleware(['api.permission:expenses.approve', 'throttle:api-write', 'api.idempotent']);
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->middleware(['api.permission:expenses.approve', 'throttle:api-write', 'api.idempotent']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('api.permission:notifications.view');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('api.permission:notifications.view');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('api.permission:notifications.view');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('api.permission:notifications.view');

        // Devices
        Route::post('/devices', [DeviceController::class, 'store'])->middleware(['api.permission:devices.manage', 'throttle:api-write']);
        Route::put('/devices/{device}', [DeviceController::class, 'update'])->middleware(['api.permission:devices.manage', 'throttle:api-write']);
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->middleware(['api.permission:devices.manage', 'throttle:api-write']);

        // Chart of Accounts (COA) - Finance & Owner Only
        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])->middleware('api.permission:coa.view');
        Route::get('/chart-of-accounts/{chart_of_account}', [ChartOfAccountController::class, 'show'])->middleware('api.permission:coa.view');
        Route::get('/coas', [ChartOfAccountController::class, 'index'])->middleware('api.permission:coa.view');
        Route::get('/coas/{chart_of_account}', [ChartOfAccountController::class, 'show'])->middleware('api.permission:coa.view');

        // References
        Route::prefix('reference')->middleware('api.permission:reference.view')->group(function () {
            Route::get('/packages', [ReferenceController::class, 'packages']);
            Route::get('/cash-bank-accounts', [ReferenceController::class, 'cashBankAccounts']);
            Route::get('/asset-accounts', [ReferenceController::class, 'assetAccounts']);
            Route::get('/revenue-accounts', [ReferenceController::class, 'revenueAccounts']);
            Route::get('/expense-accounts', [ReferenceController::class, 'expenseAccounts']);
            Route::get('/chart-of-accounts', [ReferenceController::class, 'chartOfAccounts'])->middleware('api.permission:coa.view');
            Route::get('/coas', [ReferenceController::class, 'chartOfAccounts'])->middleware('api.permission:coa.view');
        });
    });
});
