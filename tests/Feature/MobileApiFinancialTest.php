<?php

use App\Models\AccountingPeriod;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\NetworkJob;
use App\Models\OtherIncome;
use App\Models\Payment;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->finance = User::where('role', 'admin_keuangan')->firstOrFail();
    Sanctum::actingAs($this->finance, RolePermissions::forRole($this->finance->role));
});

test('payment amount and mdr are server authoritative and an idempotent retry creates one payment', function () {
    $customer = Customer::whereHas('invoices', fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']))->firstOrFail();
    $cash = CashBankAccount::where('is_active', true)->firstOrFail();
    $before = Payment::count();

    $preview = $this->postJson('/api/v1/payments/preview', [
        'customer_id' => $customer->id,
        'payment_method' => 'qris',
        'cash_bank_account_id' => $cash->id,
    ])->assertOk();
    $reference = $preview->json('data.preview_reference');
    $authoritativeAmount = $preview->json('data.gross_amount');

    $this->withHeader('Idempotency-Key', 'payment-manipulation-001')
        ->postJson('/api/v1/payments', [
            'customer_id' => $customer->id,
            'payment_method' => 'qris',
            'cash_bank_account_id' => $cash->id,
            'preview_reference' => $reference,
            'amount' => '1.00',
            'mdr_percentage' => '99.00',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

    $payload = [
        'customer_id' => $customer->id,
        'payment_method' => 'qris',
        'cash_bank_account_id' => $cash->id,
        'preview_reference' => $reference,
        'payment_date' => '2026-08-23',
    ];
    $key = 'payment-idempotency-550e8400-e29b-41d4-a716-446655440000';
    $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/payments', $payload)
        ->assertCreated()
        ->assertJsonPath('data.gross_amount', $authoritativeAmount);
    $paymentId = $first->json('data.id');

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/payments', $payload)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $paymentId);

    expect(Payment::count())->toBe($before + 1)
        ->and(Payment::findOrFail($paymentId)->allocations()->count())->toBeGreaterThan(0)
        ->and($customer->unpaidInvoices()->count())->toBe(0);
});

test('closed accounting period rejects payment posting and rolls back financial records', function () {
    $customer = Customer::whereHas('invoices', fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']))->firstOrFail();
    $cash = CashBankAccount::where('is_active', true)->firstOrFail();
    AccountingPeriod::create(['period' => '2026-09', 'status' => 'closed']);
    $preview = $this->postJson('/api/v1/payments/preview', [
        'customer_id' => $customer->id,
        'payment_method' => 'manual',
        'cash_bank_account_id' => $cash->id,
    ])->assertOk();
    $before = Payment::count();

    $this->withHeader('Idempotency-Key', 'payment-closed-period-001')->postJson('/api/v1/payments', [
        'customer_id' => $customer->id,
        'payment_method' => 'manual',
        'cash_bank_account_id' => $cash->id,
        'preview_reference' => $preview->json('data.preview_reference'),
        'payment_date' => '2026-09-10',
    ])->assertUnprocessable()->assertJsonPath('error.code', 'ACCOUNTING_PERIOD_CLOSED');

    expect(Payment::count())->toBe($before)
        ->and($customer->unpaidInvoices()->exists())->toBeTrue();
});

test('network queue failure never rolls back a posted payment', function () {
    $customer = Customer::where('status', 'isolated')
        ->whereHas('invoices', fn ($query) => $query->whereIn('status', ['unpaid', 'overdue']))
        ->firstOrFail();
    $customer->pppAccount()->delete();
    $cash = CashBankAccount::where('is_active', true)->firstOrFail();
    $preview = $this->postJson('/api/v1/payments/preview', [
        'customer_id' => $customer->id,
        'payment_method' => 'manual',
        'cash_bank_account_id' => $cash->id,
    ])->assertOk();

    $response = $this->withHeader('Idempotency-Key', 'payment-network-failure-001')
        ->postJson('/api/v1/payments', [
            'customer_id' => $customer->id,
            'payment_method' => 'manual',
            'cash_bank_account_id' => $cash->id,
            'preview_reference' => $preview->json('data.preview_reference'),
            'payment_date' => '2026-08-23',
        ])->assertCreated();

    expect(Payment::find($response->json('data.id')))->not->toBeNull()
        ->and($customer->unpaidInvoices()->count())->toBe(0)
        ->and(NetworkJob::where('target_type', 'customer')->where('target_id', $customer->id)->where('status', 'failed')->exists())->toBeTrue();
});

test('income preview and post are atomic and idempotent', function () {
    $revenue = ChartOfAccount::where('type', 'revenue')->firstOrFail();
    $cash = CashBankAccount::where('is_active', true)->firstOrFail();
    $payload = [
        'date' => '2026-08-23',
        'revenue_account_id' => $revenue->id,
        'description' => 'Mobile installation fee',
        'amount' => '500000.00',
        'cash_bank_account_id' => $cash->id,
        'reference' => 'MOBILE-INCOME-001',
    ];
    $preview = $this->postJson('/api/v1/incomes/preview', $payload)->assertOk();
    $payload['preview_reference'] = $preview->json('data.preview_reference');
    $key = 'income-idempotency-001';

    $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/incomes', $payload)
        ->assertCreated()->assertJsonPath('data.amount', '500000.00');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/incomes', $payload)
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect(OtherIncome::where('reference', 'MOBILE-INCOME-001')->count())->toBe(1)
        ->and(JournalEntry::where('reference_type', 'other_income')->where('reference_id', $first->json('data.id'))->exists())->toBeTrue();
});

test('finance submits an expense but only owner can approve it once', function () {
    $expenseAccount = ChartOfAccount::where('type', 'expense')->firstOrFail();
    $cash = CashBankAccount::where('is_active', true)->firstOrFail();
    $balanceBefore = (float) $cash->current_balance;
    $draft = $this->postJson('/api/v1/expenses', [
        'date' => '2026-08-23',
        'expense_account_id' => $expenseAccount->id,
        'cash_bank_account_id' => $cash->id,
        'amount' => '125000.00',
        'description' => 'Mobile maintenance expense',
    ])->assertCreated()->assertJsonPath('data.status', 'draft');
    $expenseId = $draft->json('data.id');

    $this->withHeader('Idempotency-Key', 'expense-submit-001')
        ->postJson("/api/v1/expenses/{$expenseId}/submit")
        ->assertOk()->assertJsonPath('data.status', 'pending_approval');

    $this->withHeader('Idempotency-Key', 'expense-finance-approve-001')
        ->postJson("/api/v1/expenses/{$expenseId}/approve")
        ->assertForbidden();

    $owner = User::where('role', 'owner')->firstOrFail();
    Sanctum::actingAs($owner, RolePermissions::forRole($owner->role));
    $this->withHeader('Idempotency-Key', 'expense-owner-approve-001')
        ->postJson("/api/v1/expenses/{$expenseId}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $this->withHeader('Idempotency-Key', 'expense-owner-approve-002')
        ->postJson("/api/v1/expenses/{$expenseId}/approve")
        ->assertConflict()->assertJsonPath('error.code', 'EXPENSE_ALREADY_PROCESSED');

    expect((float) $cash->fresh()->current_balance)->toEqualWithDelta($balanceBefore - 125000, 0.01)
        ->and(JournalEntry::where('reference_type', 'expense')->where('reference_id', $expenseId)->count())->toBe(1);
});
