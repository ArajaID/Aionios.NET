<?php

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('role', 'owner')->firstOrFail();
    $this->finance = User::where('role', 'admin_keuangan')->firstOrFail();
    $this->network = User::where('role', 'admin_jaringan')->firstOrFail();
});

test('unauthenticated user cannot access mobile coa endpoints', function () {
    $this->getJson('/api/v1/chart-of-accounts')->assertUnauthorized();
    $this->getJson('/api/v1/reference/chart-of-accounts')->assertUnauthorized();
    $this->getJson('/api/v1/coas')->assertUnauthorized();
});

test('network admin is forbidden from accessing coa endpoints', function () {
    Sanctum::actingAs($this->network, RolePermissions::forRole($this->network->role));

    $this->getJson('/api/v1/chart-of-accounts')->assertForbidden();
    $this->getJson('/api/v1/reference/chart-of-accounts')->assertForbidden();
    $this->getJson('/api/v1/coas')->assertForbidden();
});

test('finance admin and owner can list all active chart of accounts', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    $response = $this->getJson('/api/v1/chart-of-accounts')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                    'type',
                    'category',
                    'normal_balance',
                    'is_active',
                    'is_system',
                    'cash_bank_accounts',
                ],
            ],
        ]);

    expect(count($response->json('data')))->toBeGreaterThan(5);

    // Verify owner can also access via reference endpoint
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));
    $this->getJson('/api/v1/reference/chart-of-accounts')->assertOk();
    $this->getJson('/api/v1/coas')->assertOk();
});

test('can filter coas by type', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    $resExpense = $this->getJson('/api/v1/chart-of-accounts?type=expense')->assertOk();
    $expenses = $resExpense->json('data');
    expect(count($expenses))->toBeGreaterThan(0);
    foreach ($expenses as $item) {
        expect($item['type'])->toBe('expense');
    }

    $resMulti = $this->getJson('/api/v1/chart-of-accounts?type=revenue,expense')->assertOk();
    $multi = $resMulti->json('data');
    foreach ($multi as $item) {
        expect(in_array($item['type'], ['revenue', 'expense']))->toBeTrue();
    }
});

test('can filter coas by transaction usage context', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    // 1. Payment usage: Cash/Bank, AR, and MDR expense
    $paymentRes = $this->getJson('/api/v1/chart-of-accounts?usage=payment')->assertOk();
    $paymentCodes = collect($paymentRes->json('data'))->pluck('code')->all();
    expect($paymentCodes)->toContain('1110'); // Kas Kasir
    expect($paymentCodes)->toContain('1210'); // Piutang Usaha
    expect($paymentCodes)->toContain('5170'); // Beban MDR QRIS

    // 2. Income usage: Revenue and Cash/Bank
    $incomeRes = $this->getJson('/api/v1/chart-of-accounts?usage=income')->assertOk();
    $incomeTypes = collect($incomeRes->json('data'))->pluck('type')->unique()->values()->all();
    expect(array_diff($incomeTypes, ['revenue', 'asset']))->toBeEmpty();

    // 3. Expense usage: Expense and Cash/Bank
    $expenseRes = $this->getJson('/api/v1/chart-of-accounts?usage=expense')->assertOk();
    $expenseTypes = collect($expenseRes->json('data'))->pluck('type')->unique()->values()->all();
    expect(array_diff($expenseTypes, ['expense', 'asset']))->toBeEmpty();

    // 4. Billing usage: AR and Revenue
    $billingRes = $this->getJson('/api/v1/chart-of-accounts?usage=billing')->assertOk();
    $billingCodes = collect($billingRes->json('data'))->pluck('code')->all();
    expect($billingCodes)->toContain('1210');
    expect($billingCodes)->toContain('4110');
});

test('can search coa by code or name', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    $res = $this->getJson('/api/v1/chart-of-accounts?search=QRIS')->assertOk();
    $codes = collect($res->json('data'))->pluck('code')->all();
    expect($codes)->toContain('1140'); // QRIS Settlement Merchant or 5170 Beban MDR QRIS
});

test('can view single coa detail with linked cash bank accounts', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    $kas = ChartOfAccount::where('code', '1110')->firstOrFail();
    $res = $this->getJson("/api/v1/chart-of-accounts/{$kas->id}")
        ->assertOk()
        ->assertJsonPath('data.code', '1110')
        ->assertJsonPath('data.name', 'Kas Kasir Utama');

    $cashBankAccounts = $res->json('data.cash_bank_accounts');
    expect(count($cashBankAccounts))->toBeGreaterThanOrEqual(1);
    expect($cashBankAccounts[0]['name'])->toBe('Kas Tunai Kasir');
});

test('can paginate coa list', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    $res = $this->getJson('/api/v1/chart-of-accounts?per_page=5')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

    expect(count($res->json('data')))->toBe(5);
    expect($res->json('meta.per_page'))->toBe(5);
});

test('reference asset-accounts and cash-bank-accounts return coa asset data properly', function () {
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));

    // 1. Asset accounts endpoint
    $assetRes = $this->getJson('/api/v1/reference/asset-accounts')->assertOk();
    $assets = $assetRes->json('data');
    expect(count($assets))->toBeGreaterThan(0);
    expect(collect($assets)->pluck('code')->all())->toContain('1110');

    // 2. Cash & bank accounts reference with linked COA
    $cbRes = $this->getJson('/api/v1/reference/cash-bank-accounts')->assertOk();
    $cbs = $cbRes->json('data');
    expect(count($cbs))->toBeGreaterThan(0);
    expect($cbs[0])->toHaveKeys(['id', 'name', 'chart_of_account_id', 'chart_of_account_code', 'chart_of_account_name']);

    // 3. Usage cash_bank returns asset COAs
    $usageCbRes = $this->getJson('/api/v1/chart-of-accounts?usage=cash_bank')->assertOk();
    $usageCbs = $usageCbRes->json('data');
    expect(count($usageCbs))->toBeGreaterThan(0);
    foreach ($usageCbs as $item) {
        expect($item['type'])->toBe('asset');
    }
});
