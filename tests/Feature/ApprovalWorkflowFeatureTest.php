<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceAdjustmentRequest;
use App\Models\Package;
use App\Models\PackageChangeRequest;
use App\Models\PppAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*' => Http::response([], 200)]);
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('role', 'owner')->first();
    $this->staff = User::where('role', 'admin_keuangan')->first() ?? User::factory()->create(['role' => 'admin_keuangan']);
});

test('staff submitting invoice adjustment creates pending request without altering invoice', function () {
    $customer = Customer::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-001',
        'customer_id' => $customer->id,
        'period' => '2027-01',
        'issue_date' => now(),
        'due_date' => now()->addDays(7),
        'subtotal' => 200000,
        'discount_amount' => 0,
        'total_amount' => 200000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $response = $this->actingAs($this->staff)->put("/invoices/{$invoice->id}", [
        'subtotal' => 0,
        'discount_amount' => 0,
        'notes' => 'Peralihan migrasi Rp 0',
    ]);

    $response->assertSessionHas('info');

    // Invoice itself must NOT be modified yet
    $invoice->refresh();
    expect((float) $invoice->total_amount)->toEqual(200000.0);
    expect($invoice->status)->toEqual('unpaid');

    // Pending request must exist
    $request = InvoiceAdjustmentRequest::where('invoice_id', $invoice->id)->first();
    expect($request)->not->toBeNull();
    expect($request->status)->toEqual('pending');
    expect((float) $request->new_total_amount)->toEqual(0.0);
});

test('owner can approve invoice adjustment and invoice becomes paid when zero', function () {
    $customer = Customer::first();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-002',
        'customer_id' => $customer->id,
        'period' => '2027-02',
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
        'new_subtotal' => 0,
        'old_discount_amount' => 0,
        'new_discount_amount' => 0,
        'old_total_amount' => 300000,
        'new_total_amount' => 0,
        'reason' => 'Diskon promo peralihan',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->owner)->post("/approvals/invoice-adjustment/{$adj->id}/approve");
    $response->assertSessionHas('success');

    $invoice->refresh();
    $adj->refresh();

    expect($adj->status)->toEqual('approved');
    expect((float) $invoice->total_amount)->toEqual(0.0);
    expect($invoice->status)->toEqual('paid');
});

test('staff submitting package change creates pending request without altering customer package', function () {
    $customer = Customer::first();
    $pkg1 = $customer->package;
    $pkg2 = Package::where('id', '!=', $pkg1->id)->first();

    $response = $this->actingAs($this->staff)->put("/customers/{$customer->id}", [
        'name' => $customer->name,
        'phone' => $customer->phone,
        'address' => $customer->address,
        'package_id' => $pkg2->id,
        'package_change_reason' => 'Upgrade bandwidth',
    ]);

    $response->assertSessionHas('info');

    // Customer package must NOT change yet
    $customer->refresh();
    expect($customer->package_id)->toEqual($pkg1->id);

    // Pending request must exist
    $req = PackageChangeRequest::where('customer_id', $customer->id)->first();
    expect($req)->not->toBeNull();
    expect($req->status)->toEqual('pending');
    expect($req->new_package_id)->toEqual($pkg2->id);
});

test('owner can approve package change request', function () {
    $customer = Customer::first();
    $pkg1 = $customer->package;
    $pkg2 = Package::where('id', '!=', $pkg1->id)->first();

    $pkgReq = PackageChangeRequest::create([
        'customer_id' => $customer->id,
        'requested_by' => $this->staff->id,
        'old_package_id' => $pkg1->id,
        'new_package_id' => $pkg2->id,
        'reason' => 'Upgrade permintaan pelanggan',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->owner)->post("/approvals/package-change/{$pkgReq->id}/approve");
    $response->assertSessionHas('success');

    $customer->refresh();
    $pkgReq->refresh();

    expect($pkgReq->status)->toEqual('approved');
    expect($customer->package_id)->toEqual($pkg2->id);
});

test('coa can be updated and active status can be toggled', function () {
    $coa = ChartOfAccount::first();

    $response = $this->actingAs($this->owner)->put("/accounting/coa/{$coa->id}", [
        'code' => $coa->code,
        'name' => 'Akun Diubah Nama',
        'type' => $coa->type,
        'category' => $coa->category,
        'normal_balance' => $coa->normal_balance,
    ]);
    $response->assertSessionHas('success');

    $coa->refresh();
    expect($coa->name)->toEqual('Akun Diubah Nama');

    // Toggle active
    $initialStatus = $coa->is_active;
    $this->actingAs($this->owner)->post("/accounting/coa/{$coa->id}/toggle");
    $coa->refresh();
    expect($coa->is_active)->toEqual(!$initialStatus);
});
