<?php

namespace App\Services;

use App\Models\OperationalExpense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OperationalExpenseService
{
    /**
     * Ambil daftar biaya operasional, ringkasan total, serta rekap subtotal per kategori
     * sesuai scope wilayah actor dan filter aktif.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getExpensesReport(User $actor, array $filters, ?int $limit = 250): array
    {
        $baseQuery = $this->scopedExpensesQuery($actor, $filters);

        $summaryRow = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(operational_expenses.amount), 0) as total_amount')
            ->first();

        $totalCount = (int) ($summaryRow->total_count ?? 0);
        $totalAmount = round((float) ($summaryRow->total_amount ?? 0), 2);
        $averageAmount = $totalCount > 0 ? round($totalAmount / $totalCount, 2) : 0.0;

        $categoryRows = (clone $baseQuery)
            ->select('operational_expenses.category')
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(operational_expenses.amount), 0) as total_amount')
            ->groupBy('operational_expenses.category')
            ->get()
            ->keyBy('category');

        $categorySummaries = [];
        $activeCategoriesCount = 0;

        foreach (OperationalExpense::CATEGORIES as $key => $label) {
            $row = $categoryRows->get($key);
            $count = (int) ($row->expenses_count ?? 0);
            $amount = round((float) ($row->total_amount ?? 0), 2);
            $share = $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 2) : 0.0;

            if ($count > 0) {
                $activeCategoriesCount++;
            }

            $categorySummaries[] = [
                'category' => $key,
                'label' => $label,
                'count' => $count,
                'total_amount' => $amount,
                'percentage' => $share,
            ];
        }

        usort($categorySummaries, fn (array $a, array $b) => $b['total_amount'] <=> $a['total_amount']);

        $expensesQuery = (clone $baseQuery)
            ->with([
                'branch:id,code,name,regency_id,province_id',
                'branch.regency:id,name',
                'user:id,name,role',
            ])
            ->orderByDesc('operational_expenses.expense_date')
            ->orderByDesc('operational_expenses.id');

        if ($limit !== null) {
            $expensesQuery->limit($limit);
        }

        $expenses = $expensesQuery
            ->get()
            ->map(fn (OperationalExpense $expense) => [
                'id' => $expense->id,
                'branch_id' => $expense->branch_id,
                'branch' => $expense->branch ? [
                    'id' => $expense->branch->id,
                    'code' => $expense->branch->code,
                    'name' => $expense->branch->name,
                    'regency_name' => $expense->branch->regency?->name,
                ] : null,
                'user' => $expense->user ? [
                    'id' => $expense->user->id,
                    'name' => $expense->user->name,
                ] : null,
                'expense_date' => $expense->expense_date?->format('Y-m-d'),
                'category' => $expense->category,
                'category_label' => $expense->category_label,
                'description' => $expense->description,
                'amount' => round((float) $expense->amount, 2),
                'notes' => $expense->notes,
                'created_at' => $expense->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'average_amount' => $averageAmount,
                'active_categories_count' => $activeCategoriesCount,
            ],
            'category_summaries' => $categorySummaries,
            'expenses' => $expenses,
        ];
    }

    /**
     * Catat biaya operasional baru secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): OperationalExpense
    {
        return DB::transaction(function () use ($actor, $data): OperationalExpense {
            $branchId = $actor->isAdminCabang()
                ? (int) $actor->branch_id
                : (int) $data['branch_id'];

            return OperationalExpense::create([
                'branch_id' => $branchId,
                'user_id' => $actor->id,
                'expense_date' => Carbon::parse((string) $data['expense_date'])->toDateString(),
                'category' => (string) $data['category'],
                'description' => trim((string) $data['description']),
                'amount' => round((float) $data['amount'], 2),
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== ''
                    ? trim((string) $data['notes'])
                    : null,
            ]);
        });
    }

    /**
     * Perbarui data biaya operasional secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, OperationalExpense $expense, array $data): OperationalExpense
    {
        return DB::transaction(function () use ($actor, $expense, $data): OperationalExpense {
            $branchId = $actor->isAdminNasional() && ! empty($data['branch_id'])
                ? (int) $data['branch_id']
                : (int) $expense->branch_id;

            $expense->update([
                'branch_id' => $branchId,
                'expense_date' => Carbon::parse((string) $data['expense_date'])->toDateString(),
                'category' => (string) $data['category'],
                'description' => trim((string) $data['description']),
                'amount' => round((float) $data['amount'], 2),
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== ''
                    ? trim((string) $data['notes'])
                    : null,
            ]);

            return $expense->fresh(['branch', 'user']);
        });
    }

    /**
     * Soft-delete biaya operasional untuk menjaga integritas audit.
     */
    public function delete(OperationalExpense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $expense->delete();
        });
    }

    /**
     * Bangun base query OperationalExpense ter-scope via HasRegionScope & filter aktif.
     *
     * @param  array<string, mixed>  $filters
     */
    private function scopedExpensesQuery(User $actor, array $filters): Builder
    {
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse((string) $filters['start_date'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse((string) $filters['end_date'])->toDateString()
            : now()->endOfMonth()->toDateString();

        $query = OperationalExpense::forUser($actor)
            ->whereDate('operational_expenses.expense_date', '>=', $startDate)
            ->whereDate('operational_expenses.expense_date', '<=', $endDate);

        if (! empty($filters['branch_id'])) {
            $query->where('operational_expenses.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('regency_id', $filters['regency_id']));
        } elseif (! empty($filters['province_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('province_id', $filters['province_id']));
        }

        $category = (string) ($filters['category'] ?? 'all');
        if ($category !== '' && $category !== 'all') {
            $query->where('operational_expenses.category', $category);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('operational_expenses.description', 'like', "%{$search}%")
                    ->orWhere('operational_expenses.notes', 'like', "%{$search}%")
                    ->orWhereHas('branch', function (Builder $bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
