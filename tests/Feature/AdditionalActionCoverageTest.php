<?php

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\NetworkJob;
use App\Models\Notification;
use App\Models\OpeningBalance;
use App\Models\Package;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake(['*' => Http::response([], 200)]);
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('role', 'owner')->firstOrFail();
    $this->actingAs($this->owner);
});

test('customer profile can be updated and terminated customer can be reactivated', function () {
    $customer = Customer::where('status', 'active')->firstOrFail();
    $this->put("/customers/{$customer->id}", [
        'name' => 'Nama Pelanggan Diperbarui',
        'phone' => '081299999999',
        'address' => 'Alamat yang telah diperbarui',
        'notes' => 'Update feature test',
    ])->assertSessionHasNoErrors();

    expect($customer->fresh()->name)->toBe('Nama Pelanggan Diperbarui');

    $terminated = Customer::where('status', 'terminated')->firstOrFail();
    $package = Package::where('is_active', true)->firstOrFail();
    expect($terminated->outstanding_amount)->toBe(0.0);

    $this->post("/customers/{$terminated->id}/reactivate", [
        'activated_at' => '2026-08-22',
        'package_id' => $package->id,
        'ont_id' => null,
        'ppp_password' => 'password-reactivated',
        'notes' => 'Reaktivasi feature test',
    ])->assertSessionHasNoErrors();

    expect($terminated->fresh()->status)->toBe('active');
});

test('coa and balanced opening balance can be posted while unbalanced input is rejected', function () {
    $this->post('/accounting/coa', [
        'code' => '1999',
        'name' => 'Akun Test Otomatis',
        'type' => 'asset',
        'category' => 'Pengujian',
        'normal_balance' => 'debit',
    ])->assertSessionHasNoErrors();

    $debitCoa = ChartOfAccount::where('code', '1999')->firstOrFail();
    $creditCoa = ChartOfAccount::where('type', 'equity')->firstOrFail();

    $this->post('/accounting/opening-balance', [
        'date' => '2025-12-31',
        'notes' => 'Saldo awal feature test',
        'lines' => [
            ['chart_of_account_id' => $debitCoa->id, 'debit' => 100000, 'credit' => 0],
            ['chart_of_account_id' => $creditCoa->id, 'debit' => 0, 'credit' => 100000],
        ],
    ])->assertRedirect('/accounting/coa');

    expect(OpeningBalance::where('notes', 'Saldo awal feature test')->exists())->toBeTrue()
        ->and(JournalEntry::where('reference_type', 'opening_balance')->where('description', 'like', '%Saldo awal feature test%')->exists())->toBeTrue();

    $countBefore = OpeningBalance::count();
    $this->post('/accounting/opening-balance', [
        'date' => '2025-12-31',
        'notes' => 'Tidak seimbang',
        'lines' => [
            ['chart_of_account_id' => $debitCoa->id, 'debit' => 50000, 'credit' => 0],
            ['chart_of_account_id' => $creditCoa->id, 'debit' => 0, 'credit' => 40000],
        ],
    ])->assertSessionHas('error');

    expect(OpeningBalance::count())->toBe($countBefore);
});

test('owner can reject pending expense and payment reversal request', function () {
    $expense = Expense::where('status', 'pending')->firstOrFail();
    $this->post("/expenses/{$expense->id}/reject", [
        'rejection_reason' => 'Dokumen pendukung belum lengkap',
    ])->assertSessionHasNoErrors();
    expect($expense->fresh()->status)->toBe('rejected');

    $payment = Payment::where('status', 'posted')->firstOrFail();
    $this->post("/payments/{$payment->id}/reversal", [
        'reason' => 'Permintaan reversal untuk pengujian',
    ])->assertSessionHasNoErrors();

    $request = ReversalRequest::where('transaction_id', $payment->id)->firstOrFail();
    $this->post("/approvals/reversal/{$request->id}/reject", [
        'rejection_reason' => 'Pembayaran sudah terverifikasi benar',
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe('rejected')
        ->and($payment->fresh()->status)->toBe('posted');
});

test('mark all reads only visible notifications and pending network jobs can be processed', function () {
    $finance = User::where('role', 'admin_keuangan')->firstOrFail();
    $visible = Notification::create([
        'role' => 'owner', 'type' => 'info', 'title' => 'Owner', 'message' => 'Untuk owner',
    ]);
    $hidden = Notification::create([
        'user_id' => $finance->id, 'type' => 'info', 'title' => 'Finance', 'message' => 'Untuk finance',
    ]);

    $this->post('/notifications/read-all')->assertSessionHasNoErrors();
    expect($visible->fresh()->is_read)->toBeTrue()
        ->and($hidden->fresh()->is_read)->toBeFalse();

    $job = NetworkJob::create([
        'command' => 'create_secret',
        'target_type' => 'ppp_account',
        'target_id' => 999999,
        'payload' => [
            'name' => 'queued-test',
            'password' => 'secret',
            'profile' => 'default',
            'service' => 'pppoe',
        ],
        'status' => 'pending',
        'attempts' => 0,
    ]);

    $this->post('/mikrotik/process-jobs')->assertSessionHasNoErrors();
    expect($job->fresh()->status)->toBe('success')
        ->and($job->fresh()->attempts)->toBe(1);
});
