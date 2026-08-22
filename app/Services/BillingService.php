<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function generateMonthlyInvoices(?string $period = null): array
    {
        $period = $period ?? now()->format('Y-m');
        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $issueDate = $periodDate->copy();
        $dueDate = $periodDate->copy()->day(22);

        $customers = Customer::whereIn('status', ['active', 'isolated'])
            ->with(['package', 'activePromotion.promotion'])
            ->get();

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($customers as $customer) {
            // Check idempotency: does invoice for this customer + period already exist?
            $exists = Invoice::where('customer_id', $customer->id)
                ->where('period', $period)
                ->exists();

            if ($exists || !$customer->package) {
                $skippedCount++;
                continue;
            }

            $package = $customer->package;
            $subtotal = (float) $package->price;
            $discountAmount = 0.0;
            $promoInfo = null;

            // Check active promotion
            $activePromo = $customer->activePromotion;
            if ($activePromo && $activePromo->promotion && $activePromo->promotion->is_active) {
                $promo = $activePromo->promotion;
                if ($promo->type === 'price_cut') {
                    $discountAmount = max(0, $subtotal - (float) $promo->discount_value);
                    $promoInfo = [
                        'promo_id' => $promo->id,
                        'name' => $promo->name,
                        'type' => 'price_cut',
                        'special_price' => (float) $promo->discount_value,
                    ];
                } elseif ($promo->type === 'special_discount') {
                    if ($promo->discount_type === 'percentage') {
                        $discountAmount = round(($subtotal * (float) $promo->discount_value) / 100, 2);
                    } else {
                        $discountAmount = min($subtotal, (float) $promo->discount_value);
                    }
                    $promoInfo = [
                        'promo_id' => $promo->id,
                        'name' => $promo->name,
                        'type' => 'special_discount',
                        'discount_type' => $promo->discount_type,
                        'discount_value' => (float) $promo->discount_value,
                    ];
                }
            }

            $totalAmount = max(0, $subtotal - $discountAmount);
            $invoiceNumber = $this->generateInvoiceNumber($periodDate);

            $snapshot = [
                'package_id' => $package->id,
                'package_code' => $package->code,
                'package_name' => $package->name,
                'download_speed' => $package->download_speed_mbps,
                'upload_speed' => $package->upload_speed_mbps,
                'normal_price' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => $totalAmount,
                'promo' => $promoInfo,
                'calculation_type' => 'regular_monthly',
            ];

            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'period' => $period,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'is_prorata' => false,
                'snapshot_data' => $snapshot,
            ]);

            $generatedCount++;
        }

        AuditService::log(
            'generate_monthly_invoices',
            'billing',
            null,
            null,
            null,
            ['period' => $period, 'generated' => $generatedCount, 'skipped' => $skippedCount]
        );

        Notification::create([
            'role' => 'admin_keuangan',
            'type' => 'info',
            'title' => 'Tagihan Bulanan Terbit',
            'message' => "Berhasil menerbitkan {$generatedCount} tagihan untuk periode {$period}.",
            'link' => '/invoices',
        ]);

        return [
            'period' => $period,
            'generated' => $generatedCount,
            'skipped' => $skippedCount,
        ];
    }

    public function calculateProrataFirstInvoice(Customer $customer, Carbon $activationDate): Invoice
    {
        $package = $customer->package;
        $normalPrice = (float) $package->price;

        $daysInMonth = $activationDate->daysInMonth;
        // Inclusive active days: from activation day to last day of month
        $activeDays = ($daysInMonth - $activationDate->day) + 1;

        $prorataAmount = round(($normalPrice / $daysInMonth) * $activeDays, 2);
        $period = $activationDate->format('Y-m');
        $dueDate = $activationDate->copy()->addDays(7); // 7 days grace period for initial bill

        $invoiceNumber = $this->generateInvoiceNumber($activationDate);

        $snapshot = [
            'package_id' => $package->id,
            'package_name' => $package->name,
            'normal_price' => $normalPrice,
            'days_in_month' => $daysInMonth,
            'active_days' => $activeDays,
            'activation_date' => $activationDate->toDateString(),
            'formula' => "{$normalPrice} / {$daysInMonth} * {$activeDays} = {$prorataAmount}",
            'calculation_type' => 'prorata_first_bill',
        ];

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'period' => $period,
            'issue_date' => $activationDate,
            'due_date' => $dueDate,
            'subtotal' => $prorataAmount,
            'discount_amount' => 0, // First bill is ALWAYS normal price (no promo) per PRD
            'total_amount' => $prorataAmount,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'is_prorata' => true,
            'snapshot_data' => $snapshot,
        ]);
    }

    protected function generateInvoiceNumber(Carbon $date): string
    {
        $prefix = 'INV-' . $date->format('Ym') . '-';
        $count = Invoice::where('invoice_number', 'like', $prefix . '%')->count() + 1;
        return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
