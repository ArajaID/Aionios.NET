<?php

use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\Invoice;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\PppAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*' => Http::response([], 200)]);
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('role', 'owner')->firstOrFail());
});

test('internet package can be created updated and deleted with mikrotik profile sync', function () {
    $this->post('/packages', [
        'code' => 'PKG-TEST',
        'name' => 'Paket Test',
        'download_speed_mbps' => 25,
        'upload_speed_mbps' => 10,
        'price' => 225000,
        'ppp_profile' => 'PROFILE-TEST',
        'description' => 'Paket pengujian',
    ])->assertSessionHasNoErrors();

    $package = Package::where('code', 'PKG-TEST')->firstOrFail();

    $this->put("/packages/{$package->id}", [
        'name' => 'Paket Test Revisi',
        'download_speed_mbps' => 30,
        'upload_speed_mbps' => 15,
        'price' => 250000,
        'ppp_profile' => 'PROFILE-TEST',
        'description' => 'Paket telah direvisi',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($package->fresh()->name)->toBe('Paket Test Revisi');

    $this->delete("/packages/{$package->id}")->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('packages', ['id' => $package->id]);
});

test('promotion can be created assigned and cancelled', function () {
    $this->post('/promotions', [
        'code' => 'PROMO-TEST',
        'name' => 'Promo Test',
        'type' => 'special_discount',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'duration_months' => 2,
        'promo_ppp_profile' => null,
        'description' => 'Promo pengujian',
    ])->assertSessionHasNoErrors();

    $promotion = Promotion::where('code', 'PROMO-TEST')->firstOrFail();
    $customer = Customer::where('status', 'active')->firstOrFail();

    $this->post('/promotions/assign', [
        'customer_id' => $customer->id,
        'promotion_id' => $promotion->id,
        'start_date' => '2026-08-22',
    ])->assertSessionHasNoErrors();

    $assignment = CustomerPromotion::where('customer_id', $customer->id)
        ->where('promotion_id', $promotion->id)
        ->firstOrFail();

    $this->post("/promotions/{$assignment->id}/cancel")->assertSessionHasNoErrors();
    expect($assignment->fresh()->status)->toBe('cancelled');
});

test('ont can be registered assigned to customer and returned', function () {
    $this->post('/ont', [
        'ont_id' => 'ONT-TEST-001',
        'brand' => 'Huawei',
        'model' => 'HG8245H',
        'serial_number' => 'SN-TEST-001',
        'mac_address' => '00:11:22:33:44:55',
        'condition' => 'good',
        'notes' => 'Unit pengujian',
    ])->assertSessionHasNoErrors();

    $ont = Ont::where('ont_id', 'ONT-TEST-001')->firstOrFail();
    $customer = Customer::where('status', 'active')->firstOrFail();

    $this->post("/ont/{$ont->id}/assign", [
        'customer_id' => $customer->id,
        'notes' => 'Pemasangan test',
    ])->assertSessionHasNoErrors();

    expect($ont->fresh()->status)->toBe('installed')
        ->and($ont->fresh()->current_customer_id)->toBe($customer->id);

    $this->post("/ont/{$ont->id}/return", [
        'condition' => 'fair',
        'status' => 'returned',
        'notes' => 'Penarikan test',
    ])->assertSessionHasNoErrors();

    expect($ont->fresh()->status)->toBe('returned')
        ->and($ont->fresh()->current_customer_id)->toBeNull();
});

test('new customer creates ppp account prorata invoice and can be terminated', function () {
    $package = Package::where('is_active', true)->firstOrFail();

    $this->post('/customers', [
        'customer_id' => 'CUST-TEST-001',
        'name' => 'Pelanggan Pengujian',
        'phone' => '081200001111',
        'address' => 'Alamat pelanggan test',
        'installed_at' => '2026-08-22',
        'activated_at' => '2026-08-22',
        'package_id' => $package->id,
        'ont_id' => null,
        'ppp_username' => 'pppoe-test-001',
        'ppp_password' => 'secret-test',
        'promotion_id' => null,
        'notes' => 'Registrasi lewat feature test',
    ])->assertRedirect('/customers');

    $customer = Customer::where('customer_id', 'CUST-TEST-001')->firstOrFail();

    expect(PppAccount::where('customer_id', $customer->id)->exists())->toBeTrue()
        ->and(Invoice::where('customer_id', $customer->id)->where('is_prorata', true)->exists())->toBeTrue();

    $this->post("/customers/{$customer->id}/terminate", [
        'reason' => 'Terminasi pengujian',
    ])->assertSessionHasNoErrors();

    expect($customer->fresh()->status)->toBe('terminated')
        ->and($customer->pppAccount()->first()->is_active)->toBeFalse();
});
