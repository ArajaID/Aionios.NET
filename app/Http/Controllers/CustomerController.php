<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\CustomerStatusHistory;
use App\Models\Ont;
use App\Models\OntHistory;
use App\Models\Package;
use App\Models\PppAccount;
use App\Models\Promotion;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::with(['package', 'ont', 'pppAccount', 'activePromotion.promotion']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        $customers = $query->latest()->paginate(10)->withQueryString();
        $packages = Package::where('is_active', true)->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'packages' => $packages,
            'filters' => $request->only(['search', 'status', 'package_id']),
        ]);
    }

    public function create(): Response
    {
        $packages = Package::where('is_active', true)->get();
        $availableOnts = Ont::where('status', 'available')->get();
        $promotions = Promotion::where('is_active', true)->get();

        $nextId = 'CUST-' . str_pad(Customer::count() + 1, 4, '0', STR_PAD_LEFT);

        return Inertia::render('Customers/Create', [
            'packages' => $packages,
            'available_onts' => $availableOnts,
            'promotions' => $promotions,
            'suggested_customer_id' => $nextId,
        ]);
    }

    public function store(
        Request $request,
        BillingService $billingService,
        MikrotikService $mikrotikService
    ): RedirectResponse {
        $validated = $request->validate([
            'customer_id' => 'required|string|unique:customers,customer_id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string',
            'installed_at' => 'required|date',
            'activated_at' => 'required|date',
            'package_id' => 'required|exists:packages,id',
            'ont_id' => 'nullable|exists:onts,id',
            'ppp_username' => 'required|string|unique:ppp_accounts,username',
            'ppp_password' => 'required|string',
            'promotion_id' => 'nullable|exists:promotions,id',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $billingService, $mikrotikService) {
            $customer = Customer::create([
                'customer_id' => $validated['customer_id'],
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'installed_at' => $validated['installed_at'],
                'activated_at' => $validated['activated_at'],
                'package_id' => $validated['package_id'],
                'ont_id' => $validated['ont_id'] ?? null,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Assign ONT
            if (!empty($validated['ont_id'])) {
                $ont = Ont::findOrFail($validated['ont_id']);
                $ont->update([
                    'status' => 'installed',
                    'current_customer_id' => $customer->id,
                    'installed_at' => $validated['installed_at'],
                ]);

                OntHistory::create([
                    'ont_id' => $ont->id,
                    'customer_id' => $customer->id,
                    'action' => 'assigned',
                    'condition' => $ont->condition,
                    'admin_id' => Auth::id(),
                    'notes' => 'Pemasangan awal ONT pelanggan baru.',
                ]);
            }

            // Create PPP Account
            $package = Package::findOrFail($validated['package_id']);
            $pppProfile = $package->ppp_profile;

            // Handle Promo
            if (!empty($validated['promotion_id'])) {
                $promo = Promotion::findOrFail($validated['promotion_id']);
                if ($promo->type === 'speed_boost' && $promo->promo_ppp_profile) {
                    $pppProfile = $promo->promo_ppp_profile;
                }

                CustomerPromotion::create([
                    'customer_id' => $customer->id,
                    'promotion_id' => $promo->id,
                    'start_date' => Carbon::parse($validated['activated_at']),
                    'end_date' => Carbon::parse($validated['activated_at'])->addMonths($promo->duration_months),
                    'original_ppp_profile' => $package->ppp_profile,
                    'status' => 'active',
                ]);
            }

            $ppp = PppAccount::create([
                'customer_id' => $customer->id,
                'username' => $validated['ppp_username'],
                'password' => $validated['ppp_password'],
                'profile' => $pppProfile,
                'is_active' => true,
                'status' => 'connected',
                'last_sync_at' => now(),
            ]);

            // Register with MikroTik
            $mikrotikService->createPppSecret($ppp);

            // Generate First Prorata Invoice (at normal package price per PRD)
            $billingService->calculateProrataFirstInvoice($customer, Carbon::parse($validated['activated_at']));

            // Status History
            CustomerStatusHistory::create([
                'customer_id' => $customer->id,
                'old_status' => 'new',
                'new_status' => 'active',
                'reason' => 'Aktivasi pelanggan baru',
                'changed_by' => Auth::id(),
            ]);

            AuditService::log('create_customer', 'customers', 'Customer', $customer->id, null, $customer->toArray());
        });

        return redirect()->route('customers.index')->with('success', 'Pelanggan baru berhasil didaftarkan dan diaktifkan.');
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'package',
            'ont',
            'pppAccount',
            'invoices' => fn($q) => $q->latest(),
            'payments' => fn($q) => $q->with('cashBankAccount')->latest(),
            'promotions.promotion',
            'statusHistories.changer',
        ]);

        $availableOnts = Ont::where('status', 'available')->get();
        $packages = Package::where('is_active', true)->get();
        $promotions = Promotion::where('is_active', true)->get();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'available_onts' => $availableOnts,
            'packages' => $packages,
            'promotions' => $promotions,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $packages = Package::where('is_active', true)->get();
        return Inertia::render('Customers/Edit', [
            'customer' => $customer->load(['package', 'ont', 'pppAccount']),
            'packages' => $packages,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $oldValues = $customer->toArray();
        $customer->update($validated);

        AuditService::log('update_customer', 'customers', 'Customer', $customer->id, $oldValues, $customer->toArray());

        return redirect()->route('customers.show', $customer->id)->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function terminate(Request $request, Customer $customer, MikrotikService $mikrotikService): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($customer, $request, $mikrotikService) {
            $oldStatus = $customer->status;

            // Terminate MikroTik secret
            $mikrotikService->terminateCustomer($customer);

            // Release ONT if assigned
            if ($customer->ont) {
                $ont = $customer->ont;
                $ont->update([
                    'status' => 'returned',
                    'current_customer_id' => null,
                ]);

                OntHistory::create([
                    'ont_id' => $ont->id,
                    'customer_id' => $customer->id,
                    'action' => 'returned',
                    'condition' => $ont->condition,
                    'admin_id' => Auth::id(),
                    'notes' => 'Penarikan ONT karena terminasi layanan: ' . $request->reason,
                ]);
            }

            CustomerStatusHistory::create([
                'customer_id' => $customer->id,
                'old_status' => $oldStatus,
                'new_status' => 'terminated',
                'reason' => $request->reason,
                'changed_by' => Auth::id(),
            ]);

            AuditService::log('terminate_customer', 'customers', 'Customer', $customer->id, ['status' => $oldStatus], ['status' => 'terminated', 'reason' => $request->reason]);
        });

        return redirect()->route('customers.show', $customer->id)->with('success', 'Pelanggan berhasil diterminasi. Seluruh histori tetap tersimpan.');
    }

    public function reactivate(
        Request $request,
        Customer $customer,
        BillingService $billingService,
        MikrotikService $mikrotikService
    ): RedirectResponse {
        // Critical requirement from PRD Section 9 & 74: Outstanding Balance must be 0!
        if ($customer->outstanding_amount > 0) {
            return back()->with('error', 'Reaktivasi ditolak! Pelanggan masih memiliki tunggakan sebesar Rp ' . number_format($customer->outstanding_amount, 0, ',', '.') . '. Seluruh tagihan harus dilunasi terlebih dahulu.');
        }

        $validated = $request->validate([
            'activated_at' => 'required|date',
            'package_id' => 'required|exists:packages,id',
            'ont_id' => 'nullable|exists:onts,id',
            'ppp_password' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($customer, $validated, $billingService, $mikrotikService) {
            $oldStatus = $customer->status;
            $package = Package::findOrFail($validated['package_id']);

            $customer->update([
                'status' => 'active',
                'package_id' => $package->id,
                'activated_at' => $validated['activated_at'],
                'ont_id' => $validated['ont_id'] ?? $customer->ont_id,
            ]);

            // Assign new ONT if chosen
            if (!empty($validated['ont_id'])) {
                $ont = Ont::findOrFail($validated['ont_id']);
                $ont->update([
                    'status' => 'installed',
                    'current_customer_id' => $customer->id,
                    'installed_at' => $validated['activated_at'],
                ]);

                OntHistory::create([
                    'ont_id' => $ont->id,
                    'customer_id' => $customer->id,
                    'action' => 'assigned',
                    'condition' => $ont->condition,
                    'admin_id' => Auth::id(),
                    'notes' => 'Pemasangan ONT saat reaktivasi pelanggan.',
                ]);
            }

            // Restore PPP Account
            $ppp = $customer->pppAccount;
            if ($ppp) {
                $ppp->update([
                    'is_active' => true,
                    'profile' => $package->ppp_profile,
                    'status' => 'connected',
                    'password' => $validated['ppp_password'] ?: $ppp->password,
                ]);
                $mikrotikService->unisolateCustomer($customer);
            }

            // Generate first prorata bill for reactivation
            $billingService->calculateProrataFirstInvoice($customer, Carbon::parse($validated['activated_at']));

            CustomerStatusHistory::create([
                'customer_id' => $customer->id,
                'old_status' => $oldStatus,
                'new_status' => 'active',
                'reason' => 'Reaktivasi layanan pelanggan lama',
                'changed_by' => Auth::id(),
            ]);

            AuditService::log('reactivate_customer', 'customers', 'Customer', $customer->id, ['status' => $oldStatus], ['status' => 'active']);
        });

        return redirect()->route('customers.show', $customer->id)->with('success', 'Pelanggan berhasil direaktivasi.');
    }
}
