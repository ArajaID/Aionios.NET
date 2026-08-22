<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\CashBankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ReversalRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected AccountingService $accountingService;
    protected MikrotikService $mikrotikService;

    public function __construct(AccountingService $accountingService, MikrotikService $mikrotikService)
    {
        $this->accountingService = $accountingService;
        $this->mikrotikService = $mikrotikService;
    }

    public function previewPayment(
        Customer $customer,
        string $method,
        int $cashBankAccountId,
        ?float $customMdr = null
    ): array {
        $unpaidInvoices = $customer->unpaidInvoices()->orderBy('period')->get();
        if ($unpaidInvoices->isEmpty()) {
            throw new Exception("Pelanggan {$customer->name} tidak memiliki tagihan outstanding.");
        }

        $grossAmount = (float) $unpaidInvoices->sum('total_amount');
        $cashBankAccount = CashBankAccount::findOrFail($cashBankAccountId);

        $mdrPercentage = 0.0;
        $mdrFee = 0.0;
        $netAmount = $grossAmount;

        if ($method === 'qris') {
            $defaultMdr = (float) ApplicationSetting::get('default_qris_mdr', 0.7);
            $mdrPercentage = $customMdr !== null ? $customMdr : $defaultMdr;
            $mdrFee = round(($grossAmount * $mdrPercentage) / 100, 2);
            $netAmount = round($grossAmount - $mdrFee, 2);
        }

        $revenueAccount = $this->accountingService->getMappedAccount('revenue_internet');
        $mdrAccount = $this->accountingService->getMappedAccount('expense_mdr');

        $journalPreview = [
            [
                'account_code' => $cashBankAccount->chartOfAccount->code,
                'account_name' => $cashBankAccount->chartOfAccount->name,
                'debit' => $netAmount,
                'credit' => 0,
            ],
        ];

        if ($mdrFee > 0) {
            $journalPreview[] = [
                'account_code' => $mdrAccount->code,
                'account_name' => $mdrAccount->name,
                'debit' => $mdrFee,
                'credit' => 0,
            ];
        }

        $journalPreview[] = [
            'account_code' => $revenueAccount->code,
            'account_name' => $revenueAccount->name,
            'debit' => 0,
            'credit' => $grossAmount,
        ];

        return [
            'customer' => $customer,
            'invoices' => $unpaidInvoices,
            'total_invoices' => $unpaidInvoices->count(),
            'gross_amount' => $grossAmount,
            'payment_method' => $method,
            'cash_bank_account' => $cashBankAccount,
            'mdr_percentage' => $mdrPercentage,
            'mdr_fee' => $mdrFee,
            'net_amount' => $netAmount,
            'journal_preview' => $journalPreview,
        ];
    }

    public function processPayment(
        Customer $customer,
        string $method,
        int $cashBankAccountId,
        Carbon $paymentDate,
        ?float $customMdr = null,
        ?string $notes = null
    ): Payment {
        $preview = $this->previewPayment($customer, $method, $cashBankAccountId, $customMdr);

        return DB::transaction(function () use ($customer, $method, $cashBankAccountId, $paymentDate, $notes, $preview) {
            $count = Payment::whereDate('payment_date', $paymentDate->toDateString())->count() + 1;
            $paymentNumber = 'PAY-' . $paymentDate->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $customer->id,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'cash_bank_account_id' => $cashBankAccountId,
                'gross_amount' => $preview['gross_amount'],
                'mdr_percentage' => $preview['mdr_percentage'],
                'mdr_fee' => $preview['mdr_fee'],
                'net_amount' => $preview['net_amount'],
                'notes' => $notes,
                'status' => 'posted',
                'received_by' => Auth::id(),
            ]);

            // Allocate and mark all outstanding invoices as paid
            foreach ($preview['invoices'] as $invoice) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                ]);

                $invoice->update([
                    'status' => 'paid',
                    'paid_amount' => $invoice->total_amount,
                ]);
            }

            // Post financial journal
            $this->accountingService->postPaymentJournal($payment);

            // If customer was isolated, trigger auto-unisolate
            if ($customer->status === 'isolated') {
                $this->mikrotikService->unisolateCustomer($customer);
            }

            AuditService::log(
                'create_payment',
                'payments',
                'Payment',
                $payment->id,
                null,
                $payment->toArray()
            );

            Notification::create([
                'role' => 'admin_keuangan',
                'type' => 'success',
                'title' => 'Pembayaran Berhasil Dicatat',
                'message' => "Pembayaran {$payment->payment_number} pelanggan {$customer->name} sebesar Rp " . number_format($payment->gross_amount, 0, ',', '.') . " berhasil diposting.",
                'link' => '/payments',
            ]);

            return $payment;
        });
    }

    public function approveReversal(ReversalRequest $request): void
    {
        if ($request->transaction_type !== 'payment') {
            throw new Exception("Hanya pembayaran yang didukung untuk alur reversal ini saat ini.");
        }

        DB::transaction(function () use ($request) {
            $payment = Payment::findOrFail($request->transaction_id);

            // Revert invoices to unpaid
            foreach ($payment->allocations as $allocation) {
                $invoice = $allocation->invoice;
                if ($invoice) {
                    $invoice->update([
                        'status' => 'unpaid',
                        'paid_amount' => 0,
                    ]);
                }
            }

            // Mark payment reversed
            $payment->update(['status' => 'reversed']);

            // Post reversal journal
            $this->accountingService->postReversalJournal($payment, $request->reason);

            // Approve request
            $request->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // If customer was un-isolated, check if needs to be re-isolated (if past due date)
            $customer = $payment->customer;
            if ($customer && $customer->status === 'active') {
                // If has overdue unpaid invoices, re-isolate
                $hasOverdue = $customer->unpaidInvoices()->where('due_date', '<', now())->exists();
                if ($hasOverdue) {
                    $this->mikrotikService->isolateCustomer($customer);
                }
            }

            AuditService::log(
                'approve_reversal',
                'reversals',
                'Payment',
                $payment->id,
                null,
                ['reversal_request_id' => $request->id, 'reason' => $request->reason]
            );

            Notification::create([
                'role' => 'admin_keuangan',
                'type' => 'warning',
                'title' => 'Reversal Pembayaran Disetujui',
                'message' => "Reversal untuk pembayaran {$payment->payment_number} telah disetujui oleh Owner.",
                'link' => '/payments',
            ]);
        });
    }
}
