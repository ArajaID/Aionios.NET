<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\CashBankAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Services\AuditService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Payment::with(['customer', 'cashBankAccount.chartOfAccount', 'allocations.invoice', 'receiver', 'reversalRequest']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', function ($cq) use ($s) {
                        $cq->where('name', 'like', "%{$s}%")
                            ->orWhere('customer_id', 'like', "%{$s}%");
                    });
            });
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'method', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedCustomerId = $request->query('customer_id');

        // Only customers with outstanding invoices
        $customers = Customer::whereHas('invoices', fn($q) => $q->whereIn('status', ['unpaid', 'overdue']))
            ->with(['unpaidInvoices', 'package'])
            ->get();

        $cashBankAccounts = CashBankAccount::where('is_active', true)->with('chartOfAccount')->get();
        $defaultMdr = (float) ApplicationSetting::get('default_qris_mdr', 0.7);

        return Inertia::render('Payments/Create', [
            'customers' => $customers,
            'cash_bank_accounts' => $cashBankAccounts,
            'default_mdr' => $defaultMdr,
            'preselected_customer_id' => $selectedCustomerId ? (int) $selectedCustomerId : null,
        ]);
    }

    public function preview(Request $request, PaymentService $paymentService): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_method' => 'required|in:manual,qris',
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'custom_mdr' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $customer = Customer::findOrFail($request->customer_id);
            $preview = $paymentService->previewPayment(
                $customer,
                $request->payment_method,
                $request->cash_bank_account_id,
                $request->custom_mdr
            );

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request, PaymentService $paymentService): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:manual,qris',
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'custom_mdr' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $customer = Customer::findOrFail($validated['customer_id']);
            $payment = $paymentService->processPayment(
                $customer,
                $validated['payment_method'],
                $validated['cash_bank_account_id'],
                Carbon::parse($validated['payment_date']),
                $validated['custom_mdr'] ?? null,
                $validated['notes'] ?? null
            );

            return redirect()->route('payments.index')
                ->with('success', "Pembayaran {$payment->payment_number} berhasil diposting. Seluruh invoice lunas dan status jaringan pelanggan telah diperbarui.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    public function requestReversal(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        if ($payment->status === 'reversed') {
            return back()->with('error', 'Pembayaran ini sudah pernah di-reverse sebelumnya.');
        }

        if ($payment->reversalRequest && $payment->reversalRequest->status === 'pending') {
            return back()->with('warning', 'Pengajuan reversal untuk pembayaran ini sedang menunggu approval Owner.');
        }

        $revReq = ReversalRequest::create([
            'transaction_type' => 'payment',
            'transaction_id' => $payment->id,
            'requested_by' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        AuditService::log('request_payment_reversal', 'payments', 'Payment', $payment->id, null, ['reason' => $validated['reason']]);

        return back()->with('success', "Pengajuan reversal untuk pembayaran {$payment->payment_number} telah dikirim ke Owner untuk persetujuan.");
    }
}
