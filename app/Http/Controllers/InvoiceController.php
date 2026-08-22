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

    public function generate(Request $request, BillingService $billingService): RedirectResponse
    {
        $request->validate(['period' => 'nullable|string|regex:/^\d{4}-\d{2}$/']);
        $period = $request->period ?? now()->format('Y-m');

        $result = $billingService->generateMonthlyInvoices($period);

        return back()->with('success', "Generate tagihan periode {$period} selesai. Diterbitkan: {$result['generated']}, Dilewati: {$result['skipped']}.");
    }
}
