<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ont;
use App\Models\OntHistory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OntController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ont::with('currentCustomer');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('ont_id', 'like', "%{$s}%")
                    ->orWhere('serial_number', 'like', "%{$s}%")
                    ->orWhere('brand', 'like', "%{$s}%")
                    ->orWhere('model', 'like', "%{$s}%")
                    ->orWhere('mac_address', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $onts = $query->latest()->paginate(12)->withQueryString();
        $customers = Customer::where('status', 'active')->whereNull('ont_id')->get();

        $lastOnt = Ont::latest('id')->first();
        $nextId = $lastOnt ? ((int) preg_replace('/\D/', '', $lastOnt->ont_id) + 1) : 1;
        if ($nextId <= 0) {
            $nextId = Ont::count() + 1;
        }
        $suggestedOntId = 'ONT-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);

        return Inertia::render('Ont/Index', [
            'onts' => $onts,
            'customers' => $customers,
            'suggested_ont_id' => $suggestedOntId,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ont_id' => 'required|string|unique:onts,ont_id|max:50',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'serial_number' => 'required|string|unique:onts,serial_number|max:100',
            'mac_address' => 'nullable|string|max:50',
            'condition' => 'required|string|in:good,fair,bad',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'available';
        $ont = Ont::create($validated);

        OntHistory::create([
            'ont_id' => $ont->id,
            'action' => 'registered',
            'condition' => $ont->condition,
            'admin_id' => Auth::id(),
            'notes' => 'Registrasi stok ONT baru ke dalam sistem.',
        ]);

        AuditService::log('create_ont', 'ont', 'Ont', $ont->id, null, $ont->toArray());

        return back()->with('success', 'ONT baru berhasil didaftarkan ke inventori.');
    }

    public function show(Ont $ont): Response
    {
        $ont->load(['currentCustomer', 'histories.admin', 'histories.customer']);
        return Inertia::render('Ont/Show', ['ont' => $ont]);
    }

    public function assign(Request $request, Ont $ont): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $ont->update([
            'status' => 'installed',
            'current_customer_id' => $customer->id,
            'installed_at' => now(),
        ]);

        $customer->update(['ont_id' => $ont->id]);

        OntHistory::create([
            'ont_id' => $ont->id,
            'customer_id' => $customer->id,
            'action' => 'assigned',
            'condition' => $ont->condition,
            'admin_id' => Auth::id(),
            'notes' => $validated['notes'] ?: "Pemasangan ONT di lokasi pelanggan {$customer->name}.",
        ]);

        AuditService::log('assign_ont', 'ont', 'Ont', $ont->id, null, ['customer_id' => $customer->id]);

        return back()->with('success', "ONT {$ont->ont_id} berhasil dipasangkan ke pelanggan {$customer->name}.");
    }

    public function returnOnt(Request $request, Ont $ont): RedirectResponse
    {
        $validated = $request->validate([
            'condition' => 'required|in:good,fair,bad',
            'status' => 'required|in:available,returned,damaged,lost',
            'notes' => 'nullable|string',
        ]);

        $customerId = $ont->current_customer_id;
        if ($customerId) {
            Customer::where('id', $customerId)->update(['ont_id' => null]);
        }

        $ont->update([
            'status' => $validated['status'],
            'condition' => $validated['condition'],
            'current_customer_id' => null,
            'notes' => $validated['notes'],
        ]);

        OntHistory::create([
            'ont_id' => $ont->id,
            'customer_id' => $customerId,
            'action' => 'returned',
            'condition' => $validated['condition'],
            'admin_id' => Auth::id(),
            'notes' => $validated['notes'] ?: "Penarikan ONT dari pelanggan. Status sekarang: {$validated['status']}.",
        ]);

        AuditService::log('return_ont', 'ont', 'Ont', $ont->id);

        return back()->with('success', "ONT {$ont->ont_id} berhasil ditarik dan diperbarui.");
    }
}
