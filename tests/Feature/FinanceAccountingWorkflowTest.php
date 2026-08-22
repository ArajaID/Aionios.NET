<?php

use App\Models\AccountingPeriod;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\OtherIncome;
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

test('full qris payment pays every outstanding invoice and posts balanced journal', function () {
    $customer = Customer::whereHas('invoices', fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']))->firstOrFail();
    $cashAccount = CashBankAccount::where('is_active', true)->firstOrFail();
    $outstandingCount = $customer->unpaidInvoices()->count();

    $this->postJson('/payments/preview', [
        'customer_id' => $customer->id,
        'payment_method' => 'qris',
        'cash_bank_account_id' => $cashAccount->id,
        'custom_mdr' => 0.7,
    ])->assertOk()->assertJsonPath('success', true);

    $this->post('/payments', [
        'customer_id' => $customer->id,
        'payment_date' => '2026-08-22',
        'payment_method' => 'qris',
        'cash_bank_account_id' => $cashAccount->id,
        'custom_mdr' => 0.7,
        'notes' => 'Pembayaran feature test',
    ])->assertRedirect('/payments');

    $payment = Payment::where('customer_id', $customer->id)->latest('id')->firstOrFail();
    $journal = JournalEntry::where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();

    expect($payment->allocations()->count())->toBe($outstandingCount)
        ->and($customer->unpaidInvoices()->count())->toBe(0)
        ->and((float) $journal->lines()->sum('debit'))->toEqualWithDelta((float) $journal->lines()->sum('credit'), 0.01);
});

test('payment reversal restores invoice and creates reversal journal', function () {
    $customer = Customer::whereHas('invoices', fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']))->firstOrFail();
    $cashAccount = CashBankAccount::where('is_active', true)->firstOrFail();

    $this->post('/payments', [
        'customer_id' => $customer->id,
        'payment_date' => '2026-08-22',
        'payment_method' => 'manual',
        'cash_bank_account_id' => $cashAccount->id,
        'custom_mdr' => 0,
        'notes' => 'Pembayaran sebelum reversal',
    ]);

    $payment = Payment::where('customer_id', $customer->id)->latest('id')->firstOrFail();

    $this->post("/payments/{$payment->id}/reversal", [
        'reason' => 'Salah input pembayaran',
    ])->assertSessionHasNoErrors();

    $request = ReversalRequest::where('transaction_id', $payment->id)->firstOrFail();
    $this->post("/approvals/reversal/{$request->id}/approve")->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('reversed')
        ->and($request->fresh()->status)->toBe('approved')
        ->and(Invoice::where('customer_id', $customer->id)->whereIn('status', ['unpaid', 'overdue'])->exists())->toBeTrue()
        ->and(JournalEntry::where('reference_type', 'reversal')->where('reference_id', $payment->id)->exists())->toBeTrue();
});

test('expense approval changes balance and posts a balanced journal', function () {
    $expenseCoa = ChartOfAccount::where('type', 'expense')->firstOrFail();
    $cashAccount = CashBankAccount::where('is_active', true)->firstOrFail();
    $balanceBefore = (float) $cashAccount->current_balance;

    $this->post('/expenses', [
        'date' => '2026-08-22',
        'chart_of_account_id' => $expenseCoa->id,
        'cash_bank_account_id' => $cashAccount->id,
        'amount' => 125000,
        'description' => 'Pengeluaran feature test',
        'notes' => 'Menunggu persetujuan',
    ])->assertSessionHasNoErrors();

    $expense = Expense::where('description', 'Pengeluaran feature test')->firstOrFail();
    expect($expense->status)->toBe('pending');

    $this->post("/expenses/{$expense->id}/approve")->assertSessionHasNoErrors();
    $journal = JournalEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->firstOrFail();

    expect($expense->fresh()->status)->toBe('approved')
        ->and((float) $cashAccount->fresh()->current_balance)->toEqualWithDelta($balanceBefore - 125000, 0.01)
        ->and((float) $journal->lines()->sum('debit'))->toEqualWithDelta((float) $journal->lines()->sum('credit'), 0.01);
});

test('other income capital and manual journal update accounting consistently', function () {
    $revenueCoa = ChartOfAccount::where('type', 'revenue')->firstOrFail();
    $equityCoa = ChartOfAccount::where('type', 'equity')->firstOrFail();
    $cashAccount = CashBankAccount::where('is_active', true)->firstOrFail();
    $cashCoa = $cashAccount->chart_of_account_id;
    $startingBalance = (float) $cashAccount->current_balance;

    $this->post('/other-income', [
        'date' => '2026-08-22',
        'chart_of_account_id' => $revenueCoa->id,
        'cash_bank_account_id' => $cashAccount->id,
        'amount' => 300000,
        'description' => 'Pendapatan feature test',
        'reference' => 'TEST-INCOME',
    ])->assertSessionHasNoErrors();

    $this->post('/capital', [
        'date' => '2026-08-22',
        'type' => 'additional',
        'chart_of_account_id' => $equityCoa->id,
        'cash_bank_account_id' => $cashAccount->id,
        'amount' => 500000,
        'description' => 'Modal feature test',
    ])->assertSessionHasNoErrors();

    $this->post('/accounting/journals/manual', [
        'date' => '2026-08-22',
        'description' => 'Jurnal manual feature test',
        'lines' => [
            ['chart_of_account_id' => $cashCoa, 'debit' => 10000, 'credit' => 0, 'memo' => 'Debit test'],
            ['chart_of_account_id' => $equityCoa->id, 'debit' => 0, 'credit' => 10000, 'memo' => 'Kredit test'],
        ],
    ])->assertSessionHasNoErrors();

    expect(OtherIncome::where('reference', 'TEST-INCOME')->exists())->toBeTrue()
        ->and((float) $cashAccount->fresh()->current_balance)->toEqualWithDelta($startingBalance + 800000, 0.01)
        ->and(JournalEntry::where('description', 'Jurnal manual feature test')->where('is_balanced', true)->exists())->toBeTrue();
});

test('closed accounting period rejects transactions until owner reopens it', function () {
    $cashAccount = CashBankAccount::where('is_active', true)->firstOrFail();
    $equityCoa = ChartOfAccount::where('type', 'equity')->firstOrFail();

    $this->post('/accounting/periods', ['period' => '2026-09'])->assertSessionHasNoErrors();
    expect(AccountingPeriod::where('period', '2026-09')->firstOrFail()->status)->toBe('open');

    $this->post('/accounting/periods/close', ['period' => '2026-09'])->assertSessionHasNoErrors();
    $period = AccountingPeriod::where('period', '2026-09')->firstOrFail();
    expect($period->status)->toBe('closed');

    $this->post('/accounting/journals/manual', [
        'date' => '2026-09-10',
        'description' => 'Harus ditolak period lock',
        'lines' => [
            ['chart_of_account_id' => $cashAccount->chart_of_account_id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $equityCoa->id, 'debit' => 0, 'credit' => 1000],
        ],
    ])->assertSessionHas('error');

    expect(JournalEntry::where('description', 'Harus ditolak period lock')->exists())->toBeFalse();

    $this->post("/accounting/periods/{$period->id}/reopen", [
        'reopen_reason' => 'Koreksi transaksi periode September',
    ])->assertSessionHasNoErrors();

    expect($period->fresh()->status)->toBe('open');
});
