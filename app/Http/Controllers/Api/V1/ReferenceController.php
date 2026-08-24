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
     */
    public function cashBankAccounts(): JsonResponse
    {
        return ApiResponse::success(CashBankAccount::where('is_active', true)->orderBy('name')->get()->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
        ])->values());
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
