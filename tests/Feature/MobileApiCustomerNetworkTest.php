<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NetworkJob;
use App\Models\Ont;
use App\Models\Package;
use App\Models\PppAccount;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->networkAdmin = User::where('role', 'admin_jaringan')->firstOrFail();
    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));
});

test('network admin can create and idempotently activate a pending customer without exposing ppp password', function () {
    $package = Package::where('is_active', true)->firstOrFail();
    $ont = Ont::where('status', 'available')->firstOrFail();

    $created = $this->postJson('/api/v1/customers', [
        'customer_id' => 'AIO-MOBILE-001',
        'name' => 'Mobile Customer',
        'phone' => '081299998888',
        'address' => 'Jakarta Selatan',
        'package_id' => $package->id,
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    $customerId = $created->json('data.id');
    $key = 'activation-550e8400-e29b-41d4-a716-446655440000';
    $payload = [
        'activation_date' => '2026-08-23',
        'package_id' => $package->id,
        'ppp_profile_id' => 7,
        'pppoe_username' => 'aio-mobile-001',
        'pppoe_password' => 'super-secret-password',
        'ont_id' => $ont->id,
    ];

    $first = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/customers/{$customerId}/activate", $payload)
        ->assertAccepted()
        ->assertJsonPath('data.customer_status', 'active')
        ->assertJsonPath('data.network.status', 'pending');

    expect(json_encode($first->json()))->not->toContain('super-secret-password');

    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/v1/customers/{$customerId}/activate", $payload)
        ->assertAccepted()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect(PppAccount::where('customer_id', $customerId)->count())->toBe(1)
        ->and(NetworkJob::where('target_type', 'ppp_account')->where('target_id', PppAccount::where('customer_id', $customerId)->value('id'))->count())->toBe(1)
        ->and(Invoice::where('customer_id', $customerId)->where('is_prorata', true)->count())->toBe(1)
        ->and(AuditLog::where('action', 'activate_customer')->where('entity_id', $customerId)->where('source', 'MOBILE')->whereNotNull('request_id')->exists())->toBeTrue();
});

test('ont assignment rejects an unavailable unit under a locked transaction', function () {
    $customer = Customer::where('status', 'active')->whereNull('ont_id')->first();
    if (! $customer) {
        $customer = Customer::create([
            'customer_id' => 'AIO-NO-ONT',
            'name' => 'No ONT',
            'phone' => '081200000099',
            'address' => 'Test',
            'package_id' => Package::firstOrFail()->id,
            'status' => 'active',
        ]);
    }
    $installed = Ont::where('status', 'installed')->firstOrFail();

    $this->withHeader('Idempotency-Key', 'ont-assignment-conflict-001')
        ->postJson("/api/v1/customers/{$customer->id}/ont/assign", ['ont_id' => $installed->id])
        ->assertConflict()
        ->assertJsonPath('error.code', 'ONT_NOT_AVAILABLE');
});

test('reactivation is blocked while a terminated customer has outstanding invoices', function () {
    $customer = Customer::where('status', 'terminated')->firstOrFail();
    Invoice::create([
        'invoice_number' => 'INV-REACTIVATE-BLOCK',
        'customer_id' => $customer->id,
        'period' => '2026-09',
        'issue_date' => '2026-09-01',
        'due_date' => '2026-09-22',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'unpaid',
        'is_prorata' => false,
    ]);

    $this->withHeader('Idempotency-Key', 'reactivation-outstanding-001')
        ->postJson("/api/v1/customers/{$customer->id}/reactivate", [
            'activation_date' => '2026-09-10',
            'package_id' => $customer->package_id,
        ])->assertUnprocessable()
        ->assertJsonPath('error.code', 'CUSTOMER_HAS_OUTSTANDING');
});

test('network status and jobs never expose router or ppp credentials', function () {
    $status = $this->getJson('/api/v1/network/status')->assertOk();
    $jobs = $this->getJson('/api/v1/network/jobs')->assertOk();
    $serialized = json_encode([$status->json(), $jobs->json()]);

    expect($serialized)->not->toContain('RouterSecurePass2026!')
        ->not->toContain('secret123')
        ->not->toContain('aionios_api');
});
