<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\NetworkJob;
use App\Models\Notification;
use App\Models\OtherIncome;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Ringkasan Metrik Dashboard Mobile
     *
     * Menyajikan ringkasan data metrik operasional real-time yang dipersonalisasi sesuai peran/role pengguna:
     * - Keuangan: Total penerimaan hari ini, pemasukan non-langganan hari ini, dan jumlah pengeluaran pending.
     * - Pelanggan: Total pelanggan, pelanggan aktif, terisolir, dan calon pelanggan berstatus pending.
     * - Jaringan: Jumlah antrean perintah router (pending dan failed).
     * - Owner: Total antrean persetujuan (approval) yang memerlukan tindakan.
     * - Notifikasi: Jumlah pemberitahuan sistem yang belum dibaca.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = [];

        if ($user->isFinance()) {
            $data['finance'] = [
                'payments_today' => number_format((float) Payment::whereDate('payment_date', today())->where('status', 'posted')->sum('gross_amount'), 2, '.', ''),
                'income_today' => number_format((float) OtherIncome::whereDate('date', today())->sum('amount'), 2, '.', ''),
                'expenses_pending' => Expense::where('status', 'pending')->count(),
            ];
        }

        $data['customers'] = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'isolated' => Customer::where('status', 'isolated')->count(),
            'pending' => Customer::where('status', 'pending')->count(),
        ];

        if ($user->isNetwork()) {
            $data['network'] = [
                'pending_jobs' => NetworkJob::where('status', 'pending')->count(),
                'failed_jobs' => NetworkJob::where('status', 'failed')->count(),
            ];
        }

        if ($user->isOwner()) {
            $data['approvals'] = [
                'expenses' => Expense::where('status', 'pending')->count(),
            ];
        }

        $data['notifications'] = [
            'unread' => Notification::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(fn ($role) => $role->whereNull('user_id')->where('role', $user->role))
                    ->orWhere(fn ($global) => $global->whereNull('user_id')->whereNull('role'));
            })->where('is_read', false)->count(),
        ];

        return ApiResponse::success($data);
    }
}
