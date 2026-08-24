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
 * @tags Reference
 */
class ReferenceController extends Controller
{
    /**
     * List Packages Reference
     *
     * Daftar paket internet aktif untuk form registrasi atau perubahan paket pelanggan.
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
     * List Cash & Bank Accounts Reference
     *
     * Daftar akun kas dan rekening bank aktif untuk transaksi pembayaran, pemasukan, dan pengeluaran.
     * Mengambil dari tabel cash_bank_accounts atau akun COA bertipe asset (Kas & Bank).
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
     * List Asset Accounts Reference
     *
     * Daftar akun aset (asset) aktif termasuk Kas, Bank, dan Piutang dari Chart of Accounts.
     */
    public function assetAccounts(): JsonResponse
    {
        return $this->accounts('asset');
    }

    /**
     * List Revenue Accounts Reference
     *
     * Daftar akun pendapatan (revenue) aktif untuk form pemasukan lain-lain.
     */
    public function revenueAccounts(): JsonResponse
    {
        return $this->accounts('revenue');
    }

    /**
     * List Expense Accounts Reference
     *
     * Daftar akun beban (expense) aktif untuk form pengeluaran operasional.
     */
    public function expenseAccounts(): JsonResponse
    {
        return $this->accounts('expense');
    }

    /**
     * List Chart of Accounts Reference
     *
     * Daftar lengkap Chart of Accounts (COA) dengan filter konteks transaksi (payment, income, expense, billing).
     */
    public function chartOfAccounts(Request $request, ChartOfAccountController $coaController): JsonResponse
    {
        return $coaController->index($request);
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
