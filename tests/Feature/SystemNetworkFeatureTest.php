<?php

use App\Models\ApplicationSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('role', 'owner')->firstOrFail();
});

test('owner can update application settings', function () {
    $this->actingAs($this->owner)->post('/settings', [
        'brand_name' => 'Aionios Test',
        'default_qris_mdr' => 0.8,
        'invoice_due_day' => 20,
        'auto_isolate_day' => 21,
        'auto_isolate_time' => '02:30',
        'auto_isolate_enabled' => true,
    ])->assertSessionHasNoErrors();

    expect(ApplicationSetting::get('app_brand_name'))->toBe('Aionios Test')
        ->and((float) ApplicationSetting::get('default_qris_mdr'))->toBe(0.8)
        ->and((int) ApplicationSetting::get('invoice_due_day'))->toBe(20);
});

test('users can only mark notifications addressed to them or their role', function () {
    $finance = User::where('role', 'admin_keuangan')->firstOrFail();
    $network = User::where('role', 'admin_jaringan')->firstOrFail();
    $notification = Notification::create([
        'user_id' => $finance->id,
        'type' => 'info',
        'title' => 'Khusus Finance',
        'message' => 'Notifikasi pribadi finance',
    ]);

    $this->actingAs($network)->post("/notifications/{$notification->id}/read")->assertForbidden();
    expect($notification->fresh()->is_read)->toBeFalse();

    $this->actingAs($finance)->post("/notifications/{$notification->id}/read");
    expect($notification->fresh()->is_read)->toBeTrue();
});

test('mikrotik configuration preserves password and resource endpoint reports live data', function () {
    Http::fake([
        '*/rest/system/resource' => Http::response([
            'platform' => 'MikroTik',
            'version' => '7.24',
            'cpu-load' => '5',
        ], 200),
        '*/rest/ppp/active' => Http::response([
            ['name' => 'pppoe-test', 'address' => '10.0.0.2'],
        ], 200),
    ]);

    $router = MikrotikRouter::firstOrFail();
    $oldPassword = $router->password;

    $this->actingAs($this->owner)->post('/mikrotik/router', [
        'name' => 'Router Test',
        'host' => $router->host,
        'port' => $router->port,
        'username' => $router->username,
        'password' => '',
        'timeout' => 8,
        'api_type' => 'rest',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($router->fresh()->password)->toBe($oldPassword)
        ->and($router->fresh()->timeout)->toBe(8);

    $this->actingAs($this->owner)->getJson('/mikrotik/resource')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.version', '7.24')
        ->assertJsonCount(1, 'active_connections');
});

test('manual isolation toggle updates customer and ppp profile', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $customer = Customer::where('status', 'active')->whereHas('pppAccount')->firstOrFail();

    $this->actingAs($this->owner)->post("/mikrotik/toggle-isolate/{$customer->id}");
    expect($customer->fresh()->status)->toBe('isolated')
        ->and($customer->pppAccount()->first()->profile)->toBe('ISOLIR');

    $this->actingAs($this->owner)->post("/mikrotik/toggle-isolate/{$customer->id}");
    expect($customer->fresh()->status)->toBe('active');
});

test('monthly billing is idempotent and never duplicates customer period invoices', function () {
    $eligibleCustomers = Customer::whereIn('status', ['active', 'isolated'])->count();

    $this->actingAs($this->owner)->post('/invoices/generate', ['period' => '2026-09'])->assertSessionHasNoErrors();
    expect(Invoice::where('period', '2026-09')->count())->toBe($eligibleCustomers);

    $this->actingAs($this->owner)->post('/invoices/generate', ['period' => '2026-09'])->assertSessionHasNoErrors();
    expect(Invoice::where('period', '2026-09')->count())->toBe($eligibleCustomers)
        ->and(Invoice::where('period', '2026-09')->selectRaw('customer_id, count(*) as total')->groupBy('customer_id')->having('total', '>', 1)->exists())->toBeFalse();
});
