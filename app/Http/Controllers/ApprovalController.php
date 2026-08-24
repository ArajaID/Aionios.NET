<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\InvoiceAdjustmentRequest;
use App\Models\PackageChangeRequest;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\MikrotikService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(): Response
    {
        $pendingExpenses = Expense::where('status', 'pending')
            ->with(['chartOfAccount', 'cashBankAccount', 'submitter'])
            ->latest()
            ->get();

        $pendingReversals = ReversalRequest::where('status', 'pending')
            ->with('requester')
            ->latest()
            ->get()
            ->map(function ($req) {
                if ($req->transaction_type === 'payment') {
                    $req->transaction = Payment::with(['customer', 'cashBankAccount'])->find($req->transaction_id);
                }
                return $req;
            });

        $pendingInvoiceAdjustments = InvoiceAdjustmentRequest::where('status', 'pending')
            ->with(['invoice.customer', 'requester'])
            ->latest()
            ->get();

        $pendingPackageChanges = PackageChangeRequest::where('status', 'pending')
            ->with(['customer', 'requester', 'oldPackage', 'newPackage'])
            ->latest()
            ->get();

        $processedExpenses = Expense::whereIn('status', ['approved', 'rejected'])
            ->with(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])
            ->latest('approved_at')
            ->take(10)
            ->get();

        $processedReversals = ReversalRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['requester', 'approver'])
            ->latest('approved_at')
            ->take(10)
            ->get()
            ->map(function ($req) {
                if ($req->transaction_type === 'payment') {
                    $req->transaction = Payment::with(['customer', 'cashBankAccount'])->find($req->transaction_id);
                }
                return $req;
            });

        $processedInvoiceAdjustments = InvoiceAdjustmentRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['invoice.customer', 'requester', 'approver'])
            ->latest('approved_at')
            ->take(10)
            ->get();

        $processedPackageChanges = PackageChangeRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['customer', 'requester', 'oldPackage', 'newPackage', 'approver'])
            ->latest('approved_at')
            ->take(10)
            ->get();

        return Inertia::render('Approvals/Index', [
            'pending_expenses' => $pendingExpenses,
            'pending_reversals' => $pendingReversals,
            'pending_invoice_adjustments' => $pendingInvoiceAdjustments,
            'pending_package_changes' => $pendingPackageChanges,
            'processed_expenses' => $processedExpenses,
            'processed_reversals' => $processedReversals,
            'processed_invoice_adjustments' => $processedInvoiceAdjustments,
            'processed_package_changes' => $processedPackageChanges,
        ]);
    }

    public function approveReversal(ReversalRequest $reversalRequest, PaymentService $paymentService): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menyetujui reversal.');
        }

        try {
            $paymentService->approveReversal($reversalRequest);
            return back()->with('success', 'Permintaan reversal pembayaran berhasil disetujui. Jurnal pembalik telah dibuat dan invoice dikembalikan ke status outstanding.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses reversal: ' . $e->getMessage());
        }
    }

    public function rejectReversal(Request $request, ReversalRequest $reversalRequest): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menolak reversal.');
        }

        $request->validate(['rejection_reason' => 'required|string|min:3|max:500']);

        $reversalRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        AuditService::log('reject_reversal', 'reversals', 'ReversalRequest', $reversalRequest->id, null, ['reason' => $request->rejection_reason]);

        return back()->with('success', 'Permintaan reversal telah ditolak.');
    }

    public function approveInvoiceAdjustment(InvoiceAdjustmentRequest $invoiceAdjustmentRequest): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menyetujui penyesuaian invoice.');
        }

        $invoice = $invoiceAdjustmentRequest->invoice;
        if (!$invoice) {
            return back()->with('error', 'Dokumen tagihan tidak ditemukan.');
        }

        $newTotal = $invoiceAdjustmentRequest->new_total_amount;
        $snapshot = $invoice->snapshot_data ?? [];
        $snapshot['adjustment_note'] = $invoiceAdjustmentRequest->reason;
        $snapshot['adjusted_by'] = Auth::user()->name;
        $snapshot['adjusted_at'] = now()->toDateTimeString();

        $invoice->update([
            'subtotal' => $invoiceAdjustmentRequest->new_subtotal,
            'discount_amount' => $invoiceAdjustmentRequest->new_discount_amount,
            'total_amount' => $newTotal,
            'status' => $newTotal <= 0 ? 'paid' : $invoice->status,
            'paid_amount' => $newTotal <= 0 ? 0 : $invoice->paid_amount,
            'snapshot_data' => $snapshot,
        ]);

        $invoiceAdjustmentRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AuditService::log('approve_invoice_adjustment', 'billing', 'InvoiceAdjustmentRequest', $invoiceAdjustmentRequest->id, null, [
            'invoice_number' => $invoice->invoice_number,
            'new_total' => $newTotal,
        ]);

        return back()->with('success', "Penyesuaian tagihan {$invoice->invoice_number} berhasil disetujui.");
    }

    public function rejectInvoiceAdjustment(Request $request, InvoiceAdjustmentRequest $invoiceAdjustmentRequest): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menolak penyesuaian invoice.');
        }

        $request->validate(['rejection_reason' => 'required|string|min:3|max:500']);

        $invoiceAdjustmentRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        AuditService::log('reject_invoice_adjustment', 'billing', 'InvoiceAdjustmentRequest', $invoiceAdjustmentRequest->id, null, [
            'reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Pengajuan penyesuaian tagihan telah ditolak.');
    }

    public function approvePackageChange(PackageChangeRequest $packageChangeRequest, MikrotikService $mikrotikService): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menyetujui perubahan paket pelanggan.');
        }

        $customer = $packageChangeRequest->customer;
        if (!$customer) {
            return back()->with('error', 'Data pelanggan tidak ditemukan.');
        }

        $newPackage = $packageChangeRequest->newPackage;
        $customer->update(['package_id' => $newPackage->id]);

        $ppp = $customer->pppAccount;
        if ($ppp && $customer->status === 'active') {
            $activePromo = $customer->activePromotion;
            $targetProfile = ($activePromo && $activePromo->promotion?->promo_ppp_profile)
                ? $activePromo->promotion->promo_ppp_profile
                : $newPackage->ppp_profile;

            $mikrotikService->updateProfile($ppp, $targetProfile);
        }

        $packageChangeRequest->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AuditService::log('approve_package_change', 'customers', 'PackageChangeRequest', $packageChangeRequest->id, null, [
            'customer_id' => $customer->customer_id,
            'new_package' => $newPackage->name,
        ]);

        return back()->with('success', "Perubahan paket pelanggan {$customer->name} ke {$newPackage->name} berhasil disetujui & profil MikroTik telah disinkronkan.");
    }

    public function rejectPackageChange(Request $request, PackageChangeRequest $packageChangeRequest): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menolak perubahan paket.');
        }

        $request->validate(['rejection_reason' => 'required|string|min:3|max:500']);

        $packageChangeRequest->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        AuditService::log('reject_package_change', 'customers', 'PackageChangeRequest', $packageChangeRequest->id, null, [
            'reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Pengajuan perubahan paket pelanggan telah ditolak.');
    }
}

