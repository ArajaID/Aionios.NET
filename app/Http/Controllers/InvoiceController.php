<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $invoice->load(['customer.package', 'customer.ont', 'paymentAllocations.payment.cashBankAccount']);
        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
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

        $snapshot = $invoice->snapshot_data ?? [];
        $snapshot['adjustment_note'] = $validated['notes'] ?? 'Penyesuaian nominal invoice';
        $snapshot['adjusted_at'] = now()->toDateTimeString();

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
