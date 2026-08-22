<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ont;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('owner can open every application page without server errors', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $this->seed(DatabaseSeeder::class);

    $owner = User::where('role', 'owner')->firstOrFail();
    $customer = Customer::firstOrFail();
    $invoice = Invoice::firstOrFail();
    $ont = Ont::firstOrFail();

    $pages = [
        '/dashboard',
        '/customers',
        '/customers/create',
        "/customers/{$customer->id}",
        "/customers/{$customer->id}/edit",
        '/packages',
        '/promotions',
        '/ont',
        "/ont/{$ont->id}",
        '/invoices',
        "/invoices/{$invoice->id}",
        '/payments',
        '/payments/create',
        '/expenses',
        '/other-income',
        '/capital',
        '/accounting/coa',
        '/accounting/opening-balance',
        '/accounting/journals',
        '/accounting/ledger',
        '/accounting/trial-balance',
        '/accounting/periods',
        '/reports/income-statement',
        '/reports/balance-sheet',
        '/reports/cash-flow',
        '/reports/equity-changes',
        '/reports/receivables',
        '/reports/revenue',
        '/mikrotik',
        '/approvals',
        '/audit-logs',
        '/settings',
        '/users',
    ];

    $this->actingAs($owner);

    foreach ($pages as $page) {
        $this->get($page)->assertOk();
    }
});

test('non owner roles cannot open owner management pages', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);

    foreach (['/users', '/settings', '/approvals', '/audit-logs'] as $page) {
        $this->actingAs($user)->get($page)->assertForbidden();
    }
})->with(['admin_keuangan', 'admin_jaringan']);
