<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\Promotion;
use App\Services\AuditService;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(): Response
    {
        $promotions = Promotion::withCount('customerPromotions')->latest()->get();
        $activeAssignments = CustomerPromotion::with(['customer', 'promotion'])
            ->where('status', 'active')
            ->latest()
            ->get();
        $customers = Customer::where('status', 'active')->get();

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions,
            'active_assignments' => $activeAssignments,
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:promotions,code|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:speed_boost,price_cut,special_discount',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1|max:36',
            'promo_ppp_profile' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $promo = Promotion::create($validated);
        AuditService::log('create_promotion', 'promotions', 'Promotion', $promo->id, null, $promo->toArray());

        return back()->with('success', 'Promo baru berhasil dibuat.');
    }

    public function assign(Request $request, MikrotikService $mikrotikService): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'promotion_id' => 'required|exists:promotions,id',
            'start_date' => 'required|date',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $promo = Promotion::findOrFail($validated['promotion_id']);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = $startDate->copy()->addMonths($promo->duration_months);

        $assignment = CustomerPromotion::create([
            'customer_id' => $customer->id,
            'promotion_id' => $promo->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'original_ppp_profile' => $customer->package?->ppp_profile,
            'status' => 'active',
        ]);

        // If speed boost, update profile immediately
        if ($promo->type === 'speed_boost' && $promo->promo_ppp_profile && $customer->pppAccount) {
            $mikrotikService->updateProfile($customer->pppAccount, $promo->promo_ppp_profile);
        }

        AuditService::log('assign_promotion', 'promotions', 'CustomerPromotion', $assignment->id, null, [
            'customer_id' => $customer->id,
            'promo_code' => $promo->code,
        ]);

        return back()->with('success', "Promo {$promo->name} berhasil diberikan kepada {$customer->name} sampai {$endDate->format('d M Y')}.");
    }

    public function cancelAssignment(CustomerPromotion $customerPromotion, MikrotikService $mikrotikService): RedirectResponse
    {
        $customerPromotion->update(['status' => 'cancelled']);
        $customer = $customerPromotion->customer;

        if ($customer && $customer->pppAccount && $customer->status === 'active') {
            $normalProfile = $customer->package ? $customer->package->ppp_profile : 'default';
            $mikrotikService->updateProfile($customer->pppAccount, $normalProfile);
        }

        AuditService::log('cancel_promotion_assignment', 'promotions', 'CustomerPromotion', $customerPromotion->id);

        return back()->with('success', 'Penugasan promo berhasil dibatalkan dan profile dikembalikan.');
    }
}
