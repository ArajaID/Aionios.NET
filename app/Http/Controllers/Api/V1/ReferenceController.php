<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Package;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReferenceController extends Controller
{
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

    public function cashBankAccounts(): JsonResponse
    {
        return ApiResponse::success(CashBankAccount::where('is_active', true)->orderBy('name')->get()->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
        ])->values());
    }

    public function revenueAccounts(): JsonResponse
    {
        return $this->accounts('revenue');
    }

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
