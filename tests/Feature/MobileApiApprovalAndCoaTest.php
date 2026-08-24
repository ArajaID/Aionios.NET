<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceAdjustmentRequest;
use App\Models\Package;
use App\Models\PackageChangeRequest;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*' => Http::response([], 200)]);
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('role', 'owner')->first();
    $this->staff = User::where('role', 'admin_keuangan')->first() ?? User::factory()->create(['role' => 'admin_keuangan']);
});

test('api: staff requests invoice adjustment and receives 202 pending approval', function () {
    Sanctum::actingAs($this->staff, RolePermissions::forRole($this->staff->role));
    $customer = Customer::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-API-001',
        'customer_id' => $customer->id,
        'period' => '2028-01',
        'issue_date' => now(),
        'due_date' => now()->addDays(7),
        'subtotal' => 250000,
        'discount_amount' => 0,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $response = $this->postJson("/api/v1/invoices/{$invoice->id}/adjust", [
        'subtotal' => 0,
        'discount_amount' => 0,
        'notes' => 'Penyesuaian migrasi Rp 0 via API',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'approval_pending');

    $this->assertDatabaseHas('invoice_adjustment_requests', [
        'invoice_id' => $invoice->id,
        'status' => 'pending',
        'new_total_amount' => 0,
    ]);
});

test('api: owner adjusts invoice directly and marks as paid when zero', function () {
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));
    $customer = Customer::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-API-002',
        'customer_id' => $customer->id,
        'period' => '2028-02',
        'issue_date' => now(),
        'due_date' => now()->addDays(7),
        'subtotal' => 250000,
        'discount_amount' => 0,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $response = $this->postJson("/api/v1/invoices/{$invoice->id}/adjust", [
        'subtotal' => 0,
        'discount_amount' => 0,
        'notes' => 'Penyesuaian langsung oleh Owner via API',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'applied_directly')
        ->assertJsonPath('data.invoice.status', 'paid');

    $invoice->refresh();
    expect((float) $invoice->total_amount)->toEqual(0.0);
    expect($invoice->status)->toEqual('paid');
});

test('api: owner can approve and reject invoice adjustment requests', function () {
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));
    $customer = Customer::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-API-003',
        'customer_id' => $customer->id,
        'period' => '2028-03',
        'issue_date' => now(),
        'due_date' => now()->addDays(7),
        'subtotal' => 300000,
        'discount_amount' => 0,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $adj = InvoiceAdjustmentRequest::create([
        'invoice_id' => $invoice->id,
        'requested_by' => $this->staff->id,
        'old_subtotal' => 300000,
        'new_subtotal' => 150000,
        'old_discount_amount' => 0,
        'new_discount_amount' => 0,
        'old_total_amount' => 300000,
        'new_total_amount' => 150000,
        'reason' => 'Diskon khusus',
        'status' => 'pending',
    ]);

    // List approvals
    $listResponse = $this->getJson('/api/v1/approvals/invoice-adjustments');
    $listResponse->assertStatus(200);

    // Approve
    $approveResponse = $this->postJson("/api/v1/approvals/invoice-adjustments/{$adj->id}/approve");
    $approveResponse->assertStatus(200);

    $invoice->refresh();
    expect((float) $invoice->total_amount)->toEqual(150000.0);
});

test('api: staff requests customer package change and owner approves it', function () {
    $customer = Customer::first();
    $pkg1 = $customer->package;
    $pkg2 = Package::where('id', '!=', $pkg1->id)->first();

    // Staff requests
    Sanctum::actingAs($this->staff, RolePermissions::forRole($this->staff->role));
    $requestResponse = $this->postJson("/api/v1/customers/{$customer->id}/change-package", [
        'package_id' => $pkg2->id,
        'reason' => 'Pelanggan minta ganti paket via mobile',
    ]);

    $requestResponse->assertStatus(202)
        ->assertJsonPath('data.status', 'approval_pending');

    $pkgReq = PackageChangeRequest::where('customer_id', $customer->id)->first();
    expect($pkgReq)->not->toBeNull();

    // Owner approves
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));
    $approveResponse = $this->postJson("/api/v1/approvals/package-changes/{$pkgReq->id}/approve");

    $approveResponse->assertStatus(200);
    $customer->refresh();
    expect($customer->package_id)->toEqual($pkg2->id);
});

test('api: ont suggested id and store endpoints work properly', function () {
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));

    $idRes = $this->getJson('/api/v1/onts/suggested-id');
    $idRes->assertStatus(200);
    $suggestedId = $idRes->json('data.suggested_ont_id');
    expect($suggestedId)->toBeString();

    $storeRes = $this->postJson('/api/v1/onts', [
        'ont_id' => $suggestedId,
        'brand' => 'ZTE',
        'model' => 'F609',
        'serial_number' => 'ZTEG' . rand(100000, 999999),
        'mac_address' => 'AA:BB:CC:DD:EE:01',
        'condition' => 'good',
    ]);
    $storeRes->assertStatus(201)
        ->assertJsonPath('data.ont_id', $suggestedId);
});

test('api: approvals summary endpoint returns all pending count', function () {
    Sanctum::actingAs($this->owner, RolePermissions::forRole($this->owner->role));

    $response = $this->getJson('/api/v1/approvals/summary');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'pending_expenses_count',
                'pending_reversals_count',
                'pending_invoice_adjustments_count',
                'pending_package_changes_count',
                'total_pending',
            ],
        ]);
});
