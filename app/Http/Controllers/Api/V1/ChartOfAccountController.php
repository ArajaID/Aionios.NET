<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Chart of Accounts
 */
class ChartOfAccountController extends Controller
{
    /**
     * List Chart of Accounts (COA)
     *
     * Retrieve a list of Chart of Accounts with flexible transaction context filtering for mobile operations (Payment, Income, Expense, Billing).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable'],
            'category' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', 'string', 'in:payment,pembayaran,income,pemasukan,expense,pengeluaran,billing,tagihan,cash_bank,kas_bank'],
            'for' => ['nullable', 'string', 'in:payment,pembayaran,income,pemasukan,expense,pengeluaran,billing,tagihan,cash_bank,kas_bank'],
            'is_active' => ['nullable'],
            'include_cash_bank' => ['nullable'],
            'sort' => ['nullable', 'in:code,-code,name,-name,type,-type,created_at,-created_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = ChartOfAccount::query();

        // Default eager load cashBankAccounts if requested or for usage contexts
        $includeCashBank = $request->boolean('include_cash_bank', true);
        if ($includeCashBank) {
            $query->with('cashBankAccounts');
        }

        // Filter is_active (default true)
        if ($request->has('is_active')) {
            $isActiveValue = $request->input('is_active');
            if ($isActiveValue !== 'all') {
                $query->where('is_active', filter_var($isActiveValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);
            }
        } else {
            $query->where('is_active', true);
        }

        // Filter search keyword
        if ($search = ($validated['search'] ?? $request->input('q'))) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter by usage / transaction context
        $usage = $validated['usage'] ?? $validated['for'] ?? null;
        if ($usage) {
            $this->applyUsageFilter($query, $usage);
        }

        // Filter by type (string, comma-separated, or array)
        if (! empty($validated['type'])) {
            $types = is_array($validated['type'])
                ? $validated['type']
                : explode(',', (string) $validated['type']);
            $types = array_filter(array_map('trim', $types));
            if (! empty($types)) {
                $query->whereIn('type', $types);
            }
        }

        // Filter by category
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        // Sorting
        $sort = $validated['sort'] ?? 'code';
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy($column, $direction);

        // Optional pagination
        if ($request->has('per_page')) {
            $paginator = $query->paginate($validated['per_page'] ?? 20);

            return ApiResponse::paginated(
                $paginator,
                ChartOfAccountResource::collection($paginator->getCollection())->resolve()
            );
        }

        return ApiResponse::success(
            ChartOfAccountResource::collection($query->get())->resolve()
        );
    }

    /**
     * Get Single Chart of Account Detail
     *
     * Retrieve detailed information for a specific Chart of Account by its ID, including linked cash & bank accounts.
     *
     * @param ChartOfAccount $chartOfAccount
     * @return JsonResponse
     */
    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $chartOfAccount->load('cashBankAccounts');

        return ApiResponse::success(
            (new ChartOfAccountResource($chartOfAccount))->resolve()
        );
    }

    private function applyUsageFilter(Builder $query, string $usage): void
    {
        switch ($usage) {
            case 'payment':
            case 'pembayaran':
                // Relevant for payments: Cash & Bank (Asset), Accounts Receivable (Asset), and QRIS MDR (Expense)
                $query->where(function (Builder $builder) {
                    $builder->where(function (Builder $sub) {
                        $sub->where('type', 'asset')
                            ->where(function (Builder $cat) {
                                $cat->where('category', 'like', '%Kas%')
                                    ->orWhere('category', 'like', '%Bank%')
                                    ->orWhere('category', 'like', '%Piutang%')
                                    ->orWhere('code', 'like', '11%')
                                    ->orWhere('code', '1210')
                                    ->orWhereHas('cashBankAccounts');
                            });
                    })->orWhere('code', '5170'); // MDR Fee account
                });
                break;

            case 'income':
            case 'pemasukan':
                // Relevant for other income: Revenue accounts and receiving Cash/Bank accounts
                $query->where(function (Builder $builder) {
                    $builder->where('type', 'revenue')
                        ->orWhere(function (Builder $sub) {
                            $sub->where('type', 'asset')
                                ->where(function (Builder $cat) {
                                    $cat->where('category', 'like', '%Kas%')
                                        ->orWhere('category', 'like', '%Bank%')
                                        ->orWhere('code', 'like', '11%')
                                        ->orWhereHas('cashBankAccounts');
                                });
                        });
                });
                break;

            case 'expense':
            case 'pengeluaran':
                // Relevant for expense vouchers: Expense accounts and paying Cash/Bank accounts
                $query->where(function (Builder $builder) {
                    $builder->where('type', 'expense')
                        ->orWhere(function (Builder $sub) {
                            $sub->where('type', 'asset')
                                ->where(function (Builder $cat) {
                                    $cat->where('category', 'like', '%Kas%')
                                        ->orWhere('category', 'like', '%Bank%')
                                        ->orWhere('code', 'like', '11%')
                                        ->orWhereHas('cashBankAccounts');
                                });
                        });
                });
                break;

            case 'billing':
            case 'tagihan':
                // Relevant for customer billing & invoices: Accounts Receivable and Revenue accounts
                $query->where(function (Builder $builder) {
                    $builder->where('type', 'revenue')
                        ->orWhere(function (Builder $sub) {
                            $sub->where('type', 'asset')
                                ->where(function (Builder $cat) {
                                    $cat->where('category', 'like', '%Piutang%')
                                        ->orWhere('code', '1210');
                                });
                        });
                });
                break;

            case 'cash_bank':
            case 'kas_bank':
                // Only Cash & Bank asset accounts
                $query->where('type', 'asset')
                    ->where(function (Builder $builder) {
                        $builder->where('category', 'like', '%Kas%')
                            ->orWhere('category', 'like', '%Bank%')
                            ->orWhere('code', 'like', '11%')
                            ->orWhereHas('cashBankAccounts');
                    });
                break;
        }
    }
}
