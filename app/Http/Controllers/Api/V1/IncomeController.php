<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IncomePreviewRequest;
use App\Http\Requests\Api\V1\StoreIncomeRequest;
use App\Http\Resources\Api\V1\IncomeResource;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\OtherIncome;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\PreviewReferenceService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class IncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = OtherIncome::with(['chartOfAccount', 'cashBankAccount']);
        if ($search = ($validated['search'] ?? null)) {
            $query->where(fn ($builder) => $builder
                ->where('income_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%"));
        }
        $paginator = $query->latest()->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, IncomeResource::collection($paginator->getCollection())->resolve());
    }

    public function show(OtherIncome $income): JsonResponse
    {
        return ApiResponse::success((new IncomeResource($income->load(['chartOfAccount', 'cashBankAccount'])))->resolve());
    }

    public function preview(
        IncomePreviewRequest $request,
        AccountingService $accounting,
        PreviewReferenceService $references
    ): JsonResponse {
        $payload = $this->normalized($request->validated());
        try {
            $date = Carbon::parse($payload['date']);
            $accounting->assertPeriodOpen($date);
            $revenue = ChartOfAccount::findOrFail($payload['revenue_account_id']);
            $cash = CashBankAccount::with('chartOfAccount')->findOrFail($payload['cash_bank_account_id']);
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), 'INCOME_PREVIEW_FAILED', 422);
        }

        $reference = $references->issue('income', $request->user()->id, $payload);

        return ApiResponse::success([
            'preview_reference' => $reference,
            'expires_in_seconds' => 600,
            'date' => $payload['date'],
            'description' => $payload['description'],
            'amount' => $payload['amount'],
            'reference' => $payload['reference'],
            'journal_preview' => [
                [
                    'account_code' => $cash->chartOfAccount->code,
                    'account_name' => $cash->chartOfAccount->name,
                    'debit' => $payload['amount'],
                    'credit' => '0.00',
                ],
                [
                    'account_code' => $revenue->code,
                    'account_name' => $revenue->name,
                    'debit' => '0.00',
                    'credit' => $payload['amount'],
                ],
            ],
        ]);
    }

    public function store(
        StoreIncomeRequest $request,
        AccountingService $accounting,
        PreviewReferenceService $references
    ): JsonResponse {
        $data = $request->validated();
        $payload = $this->normalized($data);
        $stored = $references->get('income', $data['preview_reference'], $request->user()->id);
        if (! $stored) {
            return ApiResponse::error('Income preview is invalid or expired.', 'INCOME_PREVIEW_EXPIRED', 409);
        }
        if ($stored !== $payload) {
            return ApiResponse::error('Income payload does not match the preview.', 'INCOME_PREVIEW_MISMATCH', 409);
        }

        try {
            $income = DB::transaction(function () use ($payload, $request, $accounting) {
                $date = Carbon::parse($payload['date']);
                $accounting->assertPeriodOpen($date);
                $revenue = ChartOfAccount::whereKey($payload['revenue_account_id'])->where('type', 'revenue')->lockForUpdate()->firstOrFail();
                $cash = CashBankAccount::whereKey($payload['cash_bank_account_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();

                $income = OtherIncome::create([
                    'income_number' => 'INC-'.$date->format('Ymd').'-'.Str::upper(Str::random(10)),
                    'date' => $date,
                    'chart_of_account_id' => $revenue->id,
                    'cash_bank_account_id' => $cash->id,
                    'amount' => $payload['amount'],
                    'description' => $payload['description'],
                    'reference' => $payload['reference'],
                    'created_by' => $request->user()->id,
                ]);
                $cash->increment('current_balance', $payload['amount']);
                $accounting->createBalancedJournal('other_income', $income->id, $date, "Other income #{$income->income_number}: {$income->description}", [
                    ['chart_of_account_id' => $cash->chart_of_account_id, 'debit' => $payload['amount'], 'credit' => 0, 'memo' => "Receipt to {$cash->name}"],
                    ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => $payload['amount'], 'memo' => $payload['description']],
                ]);
                AuditService::log('create_other_income', 'accounting', 'OtherIncome', $income->id, null, $income->toArray());

                return $income;
            });
        } catch (Throwable $e) {
            $code = str_contains(mb_strtolower($e->getMessage()), 'periode akuntansi') ? 'ACCOUNTING_PERIOD_CLOSED' : 'INCOME_POST_FAILED';

            return ApiResponse::error($e->getMessage(), $code, 422);
        }

        $references->forget('income', $data['preview_reference']);

        return ApiResponse::success(
            (new IncomeResource($income->load(['chartOfAccount', 'cashBankAccount'])))->resolve(),
            'Income posted successfully.',
            201,
        );
    }

    private function normalized(array $data): array
    {
        return [
            'date' => Carbon::parse($data['date'])->toDateString(),
            'revenue_account_id' => (int) $data['revenue_account_id'],
            'description' => $data['description'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'cash_bank_account_id' => (int) $data['cash_bank_account_id'],
            'reference' => $data['reference'] ?? null,
        ];
    }
}
