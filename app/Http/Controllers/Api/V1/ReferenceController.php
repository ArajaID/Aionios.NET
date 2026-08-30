<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Package;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Referensi Data (Reference)
 */
class ReferenceController extends Controller
{
    /**
     * Referensi Paket Internet (Packages)
     *
     * Mengambil daftar paket internet aktif untuk kebutuhan form registrasi pelanggan baru atau perubahan paket layanan.
     *
     * @return JsonResponse
     */
    public function packages(): JsonResponse
    {
        return ApiResponse::success(Package::where('is_active', true)->orderBy('name')->get()->map(fn ($package) => [
            'id' => $package->id,
            'code' => $package->code,
            'name' => $package->name,
            'download_speed_mbps' => $package->download_speed_mbps,
            'upload_speed_mbps' => $package->upload_speed_mbps,
            'price' => number_format((float) $package->price, 2, '.', ''),
        ])->values());
    }

    /**
     * Referensi Akun Kas & Bank
     *
     * Mengambil daftar akun kas dan rekening bank aktif untuk transaksi pembayaran invoice, penerimaan kas, atau pengeluaran operasional.
     *
     * @return JsonResponse
     */
    public function cashBankAccounts(): JsonResponse
    {
        $accounts = CashBankAccount::with('chartOfAccount')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($accounts->isNotEmpty()) {
            return ApiResponse::success($accounts->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'chart_of_account_id' => $account->chart_of_account_id,
                'chart_of_account_code' => $account->chartOfAccount?->code,
                'chart_of_account_name' => $account->chartOfAccount?->name,
            ])->values());
        }

        // Fallback mengambil langsung dari COA asset (Kas & Bank)
        $coaAssets = ChartOfAccount::where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('category', 'like', '%Kas%')
                    ->orWhere('category', 'like', '%Bank%')
                    ->orWhere('code', 'like', '11%');
            })
            ->orderBy('code')
            ->get();

        if ($coaAssets->isEmpty()) {
            $coaAssets = ChartOfAccount::where('type', 'asset')
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return ApiResponse::success($coaAssets->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'bank_name' => $account->name,
            'account_number' => $account->code,
            'chart_of_account_id' => $account->id,
            'chart_of_account_code' => $account->code,
            'chart_of_account_name' => $account->name,
        ])->values());
    }

    /**
     * Referensi Akun Aset (Assets)
     *
     * Mengambil daftar akun bertipe aset (Kas, Bank, Piutang) dari Chart of Accounts untuk referensi transaksi.
     *
     * @return JsonResponse
     */
    public function assetAccounts(): JsonResponse
    {
        return $this->accounts('asset');
    }

    /**
     * Referensi Akun Pendapatan (Revenue)
     *
     * Mengambil daftar akun pendapatan aktif untuk form input transaksi pemasukan lain-lain non-langganan.
     *
     * @return JsonResponse
     */
    public function revenueAccounts(): JsonResponse
    {
        return $this->accounts('revenue');
    }

    /**
     * Referensi Akun Beban (Expenses)
     *
     * Mengambil daftar akun beban aktif untuk form pencatatan dan pengajuan pengeluaran kas operasional.
     *
     * @return JsonResponse
     */
    public function expenseAccounts(): JsonResponse
    {
        return $this->accounts('expense');
    }

    private function accounts(string $type): JsonResponse
    {
        return ApiResponse::success(ChartOfAccount::where('type', $type)->where('is_active', true)->orderBy('code')->get()->map(fn ($account) => [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
        ])->values());
    }
}
