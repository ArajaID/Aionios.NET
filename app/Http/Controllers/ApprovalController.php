<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Services\AccountingService;
use App\Services\AuditService;
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

        return Inertia::render('Approvals/Index', [
            'pending_expenses' => $pendingExpenses,
            'pending_reversals' => $pendingReversals,
            'processed_expenses' => $processedExpenses,
            'processed_reversals' => $processedReversals,
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
}
