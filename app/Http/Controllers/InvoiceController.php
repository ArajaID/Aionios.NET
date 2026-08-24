<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceAdjustmentRequest;
use App\Services\AuditService;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Invoice::with('customer.package');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', function ($cq) use ($s) {
                        $cq->where('name', 'like', "%{$s}%")
                            ->orWhere('customer_id', 'like', "%{$s}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        $invoices = $query->latest()->paginate(15)->withQueryString();

        $periods = Invoice::select('period')->distinct()->orderBy('period', 'desc')->pluck('period');

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'periods' => $periods,
            'filters' => $request->only(['search', 'status', 'period']),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'customer.package',
            'customer.ont',
            'paymentAllocations.payment.cashBankAccount',
            'pendingAdjustmentRequest.requester',
            'adjustmentRequests' => fn($q) => $q->with(['requester', 'approver'])->latest(),
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'is_owner' => Auth::user()?->isOwner() ?? false,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === 'paid') {
            return back()->with('error', 'Tagihan yang sudah berstatus lunas tidak dapat diubah.');
        }

        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $subtotal = (float) $validated['subtotal'];
        $discount = (float) ($validated['discount_amount'] ?? 0);
        $total = max(0, $subtotal - $discount);

        $user = Auth::user();

        // If user is Owner, apply the change immediately
        if ($user && $user->isOwner()) {
            $snapshot = $invoice->snapshot_data ?? [];
            $snapshot['adjustment_note'] = $validated['notes'] ?? 'Penyesuaian nominal invoice oleh Owner';
            $snapshot['adjusted_at'] = now()->toDateTimeString();

            \App\Models\InvoiceAdjustmentRequest::create([
                'invoice_id' => $invoice->id,
                'requested_by' => $user->id,
                'old_subtotal' => $invoice->subtotal,
                'new_subtotal' => $subtotal,
                'old_discount_amount' => $invoice->discount_amount,
                'new_discount_amount' => $discount,
                'old_total_amount' => $invoice->total_amount,
                'new_total_amount' => $total,
                'reason' => $validated['notes'] ?? 'Penyesuaian langsung oleh Owner',
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'status' => $total <= 0 ? 'paid' : $invoice->status,
                'paid_amount' => $total <= 0 ? 0 : $invoice->paid_amount,
                'snapshot_data' => $snapshot,
            ]);

            return back()->with('success', "Nominal tagihan {$invoice->invoice_number} berhasil disesuaikan menjadi Rp " . number_format($total, 0, ',', '.') . ".");
        }

        // If user is Staff (Admin Keuangan / Admin Jaringan), create an approval request
        if ($invoice->pendingAdjustmentRequest()->exists()) {
            return back()->with('warning', "Tagihan {$invoice->invoice_number} sedang dalam antrean menunggu persetujuan Owner.");
        }

        \App\Models\InvoiceAdjustmentRequest::create([
            'invoice_id' => $invoice->id,
            'requested_by' => $user->id,
            'old_subtotal' => $invoice->subtotal,
            'new_subtotal' => $subtotal,
            'old_discount_amount' => $invoice->discount_amount,
            'new_discount_amount' => $discount,
            'old_total_amount' => $invoice->total_amount,
            'new_total_amount' => $total,
            'reason' => $validated['notes'] ?? 'Pengajuan penyesuaian nominal oleh staf',
            'status' => 'pending',
        ]);

        return back()->with('info', "Pengajuan penyesuaian tagihan {$invoice->invoice_number} berhasil diajukan dan menunggu persetujuan Owner di menu Approvals.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === 'paid' || $invoice->paymentAllocations()->exists()) {
            return back()->with('error', 'Tagihan yang sudah memiliki riwayat pembayaran tidak dapat dihapus.');
        }

        $number = $invoice->invoice_number;
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', "Tagihan {$number} berhasil dihapus.");
    }

    public function generate(Request $request, BillingService $billingService): RedirectResponse
    {
        $request->validate(['period' => 'nullable|string|regex:/^\d{4}-\d{2}$/']);
        $period = $request->period ?? now()->format('Y-m');

        $result = $billingService->generateMonthlyInvoices($period);

        return back()->with('success', "Generate tagihan periode {$period} selesai. Diterbitkan: {$result['generated']}, Dilewati: {$result['skipped']}.");
    }
}
