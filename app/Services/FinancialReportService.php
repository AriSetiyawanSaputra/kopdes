<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportService
{
    /**
     * Compute hierarchical dashboard KPIs, payment method breakdown (cash vs midtrans),
     * and branch performance summaries based strictly on paid transactions and HPP snapshots (FR-15).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDashboardSummary(User $actor, array $filters): array
    {
        $baseQuery = $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true);

        $kpiRow = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as total_cogs')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.total_cogs), 0) as gross_profit')
            ->first();

        $totalTransactions = (int) ($kpiRow->total_transactions ?? 0);
        $totalRevenue = round((float) ($kpiRow->total_revenue ?? 0), 2);
        $totalCogs = round((float) ($kpiRow->total_cogs ?? 0), 2);
        $grossProfit = round((float) ($kpiRow->gross_profit ?? 0), 2);
        $grossMarginPercent = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 2)
            : 0.0;

        $paymentRows = (clone $baseQuery)
            ->select('transactions.payment_method')
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.total_cogs), 0) as gross_profit')
            ->groupBy('transactions.payment_method')
            ->get()
            ->keyBy('payment_method');

        $cashRow = $paymentRows->get('cash');
        $midtransRow = $paymentRows->get('midtrans');

        $branchBreakdown = $this->aggregateByBranch($actor, $filters);

        return [
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'total_cogs' => $totalCogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $grossMarginPercent,
            ],
            'payment_breakdown' => [
                'cash' => [
                    'transactions_count' => (int) ($cashRow->transactions_count ?? 0),
                    'total_revenue' => round((float) ($cashRow->total_revenue ?? 0), 2),
                    'gross_profit' => round((float) ($cashRow->gross_profit ?? 0), 2),
                ],
                'midtrans' => [
                    'transactions_count' => (int) ($midtransRow->transactions_count ?? 0),
                    'total_revenue' => round((float) ($midtransRow->total_revenue ?? 0), 2),
                    'gross_profit' => round((float) ($midtransRow->gross_profit ?? 0), 2),
                ],
            ],
            'branch_summaries' => $branchBreakdown,
        ];
    }

    /**
     * Compute detailed sales & gross profit report (branch aggregation + paid transaction ledger)
     * strictly using transactions.total_amount - transactions.total_cogs and transaction_items.*_snapshot (FR-16).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesReport(User $actor, array $filters): array
    {
        $baseQuery = $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('transactions.invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('member', function (Builder $mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                            ->orWhere('member_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('branch', function (Builder $bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $kpiRow = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as total_cogs')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.total_cogs), 0) as gross_profit')
            ->first();

        $totalTransactions = (int) ($kpiRow->total_transactions ?? 0);
        $totalRevenue = round((float) ($kpiRow->total_revenue ?? 0), 2);
        $totalCogs = round((float) ($kpiRow->total_cogs ?? 0), 2);
        $grossProfit = round((float) ($kpiRow->gross_profit ?? 0), 2);
        $grossMarginPercent = $totalRevenue > 0
            ? round(($grossProfit / $totalRevenue) * 100, 2)
            : 0.0;

        $branchSummaries = $this->aggregateByBranch($actor, $filters);

        $transactions = (clone $baseQuery)
            ->with([
                'branch:id,code,name,regency_id,province_id',
                'cashier:id,name',
                'member:id,member_code,name,home_branch_id',
                'memberHomeBranch:id,code,name',
                'items',
            ])
            ->orderByDesc('transactions.paid_at')
            ->orderByDesc('transactions.id')
            ->limit(250)
            ->get()
            ->map(function (Transaction $tx) {
                $revenue = round((float) $tx->total_amount, 2);
                $cogs = round((float) $tx->total_cogs, 2);
                $profit = round($revenue - $cogs, 2);
                $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

                return [
                    'id' => $tx->id,
                    'invoice_number' => $tx->invoice_number,
                    'paid_at' => $tx->paid_at?->format('Y-m-d H:i:s'),
                    'payment_method' => $tx->payment_method,
                    'midtrans_payment_type' => $tx->midtrans_payment_type,
                    'payment_status' => $tx->payment_status,
                    'branch_id' => $tx->branch_id,
                    'branch' => $tx->branch,
                    'cashier' => $tx->cashier,
                    'member' => $tx->member ? [
                        'id' => $tx->member->id,
                        'member_code' => $tx->member->member_code,
                        'name' => $tx->member->name,
                        'home_branch_name' => $tx->memberHomeBranch?->name,
                    ] : null,
                    'total_amount' => $revenue,
                    'total_cogs' => $cogs,
                    'gross_profit' => $profit,
                    'gross_margin_percent' => $margin,
                    'items_count' => (int) $tx->items->sum('qty'),
                    'items' => $tx->items->map(fn (TransactionItem $item) => [
                        'id' => $item->id,
                        'product_name_snapshot' => $item->product_name_snapshot,
                        'unit_snapshot' => $item->unit_snapshot,
                        'qty' => (int) $item->qty,
                        'unit_price_snapshot' => round((float) $item->unit_price_snapshot, 2),
                        'cost_price_snapshot' => round((float) $item->cost_price_snapshot, 2),
                        'subtotal' => round((float) $item->subtotal, 2),
                        'subtotal_cogs' => round((float) $item->subtotal_cogs, 2),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'total_cogs' => $totalCogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $grossMarginPercent,
            ],
            'branch_summaries' => $branchSummaries,
            'transactions' => $transactions,
        ];
    }

    /**
     * Compute cross-branch member shopping recap as the SHU data foundation (FR-17).
     * Scopes members by home_branch_id / homeBranch hierarchy so an admin_cabang sees all
     * paid transactions of their registered members, including spending at other village branches.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMemberReport(User $actor, array $filters): array
    {
        [$startAt, $endAt] = $this->resolveDateBounds($filters);
        $paymentMethod = (string) ($filters['payment_method'] ?? 'all');
        $provinceId = $filters['province_id'] ?? null;
        $regencyId = $filters['regency_id'] ?? null;
        $branchId = $filters['branch_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        $membersQuery = Member::forUser($actor)
            ->with(['homeBranch:id,code,name,regency_id,province_id'])
            ->orderBy('member_code');

        if ($branchId !== null && $branchId !== '') {
            $membersQuery->where('members.home_branch_id', (int) $branchId);
        } elseif ($regencyId !== null && $regencyId !== '') {
            $membersQuery->whereHas('homeBranch', fn (Builder $q) => $q->where('regency_id', $regencyId));
        } elseif ($provinceId !== null && $provinceId !== '') {
            $membersQuery->whereHas('homeBranch', fn (Builder $q) => $q->where('province_id', $provinceId));
        }

        if ($search !== '') {
            $membersQuery->where(function (Builder $q) use ($search) {
                $q->where('members.member_code', 'like', "%{$search}%")
                    ->orWhere('members.name', 'like', "%{$search}%")
                    ->orWhere('members.phone', 'like', "%{$search}%");
            });
        }

        $members = $membersQuery->get();
        $memberIds = $members->pluck('id')->all();

        $txAggregates = collect();

        if (! empty($memberIds)) {
            $txQuery = Transaction::query()
                ->join('members', 'members.id', '=', 'transactions.member_id')
                ->whereIn('transactions.member_id', $memberIds)
                ->where('transactions.payment_status', 'paid')
                ->whereBetween('transactions.paid_at', [$startAt, $endAt]);

            if (in_array($paymentMethod, ['cash', 'midtrans'], true)) {
                $txQuery->where('transactions.payment_method', $paymentMethod);
            }

            $txAggregates = $txQuery
                ->groupBy('transactions.member_id')
                ->select('transactions.member_id')
                ->selectRaw('COUNT(transactions.id) as paid_transactions_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN transactions.branch_id = COALESCE(transactions.member_home_branch_id, members.home_branch_id) THEN transactions.total_amount ELSE 0 END), 0) as home_branch_spend')
                ->selectRaw('COALESCE(SUM(CASE WHEN transactions.branch_id <> COALESCE(transactions.member_home_branch_id, members.home_branch_id) THEN transactions.total_amount ELSE 0 END), 0) as other_branch_spend')
                ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as total_spend')
                ->get()
                ->keyBy('member_id');
        }

        $totalTransactions = 0;
        $totalHomeSpend = 0.0;
        $totalOtherSpend = 0.0;
        $totalAccumulatedSpend = 0.0;
        $activeShoppingMembers = 0;

        $rows = $members->map(function (Member $member) use (
            $txAggregates,
            &$totalTransactions,
            &$totalHomeSpend,
            &$totalOtherSpend,
            &$totalAccumulatedSpend,
            &$activeShoppingMembers
        ) {
            $agg = $txAggregates->get($member->id);
            $txCount = (int) ($agg->paid_transactions_count ?? 0);
            $homeSpend = round((float) ($agg->home_branch_spend ?? 0), 2);
            $otherSpend = round((float) ($agg->other_branch_spend ?? 0), 2);
            $totalSpend = round((float) ($agg->total_spend ?? 0), 2);

            if ($txCount > 0) {
                $activeShoppingMembers++;
            }

            $totalTransactions += $txCount;
            $totalHomeSpend = round($totalHomeSpend + $homeSpend, 2);
            $totalOtherSpend = round($totalOtherSpend + $otherSpend, 2);
            $totalAccumulatedSpend = round($totalAccumulatedSpend + $totalSpend, 2);

            return [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'name' => $member->name,
                'status' => $member->status,
                'home_branch_id' => $member->home_branch_id,
                'home_branch' => $member->homeBranch ? [
                    'id' => $member->homeBranch->id,
                    'code' => $member->homeBranch->code,
                    'name' => $member->homeBranch->name,
                ] : null,
                'paid_transactions_count' => $txCount,
                'home_branch_spend' => $homeSpend,
                'other_branch_spend' => $otherSpend,
                'total_spend' => $totalSpend,
            ];
        })
            ->sortByDesc('total_spend')
            ->values()
            ->all();

        return [
            'summary' => [
                'total_members' => count($rows),
                'active_shopping_members' => $activeShoppingMembers,
                'total_transactions' => $totalTransactions,
                'total_home_branch_spend' => $totalHomeSpend,
                'total_other_branch_spend' => $totalOtherSpend,
                'total_accumulated_spend' => $totalAccumulatedSpend,
            ],
            'members' => $rows,
        ];
    }

    /**
     * Build dynamic region drill-down options scoped strictly to the actor's role hierarchy.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDrillDownOptions(User $actor, array $filters): array
    {
        $provinceId = $filters['province_id'] ?? null;
        $regencyId = $filters['regency_id'] ?? null;

        $provinces = $actor->isAdminNasional()
            ? Province::orderBy('name')->get(['id', 'name'])
            : Province::where('id', $actor->province_id)->get(['id', 'name']);

        $regenciesQuery = Regency::orderBy('name');
        if ($actor->isAdminNasional()) {
            if ($provinceId) {
                $regenciesQuery->where('province_id', $provinceId);
            }
        } elseif ($actor->isAdminProvinsi()) {
            $regenciesQuery->where('province_id', $actor->province_id);
        } else {
            $regenciesQuery->where('id', $actor->regency_id);
        }

        $branchesQuery = Branch::forUser($actor)->orderBy('code');
        if ($provinceId) {
            $branchesQuery->where('province_id', $provinceId);
        }
        if ($regencyId) {
            $branchesQuery->where('regency_id', $regencyId);
        }

        return [
            'provinces' => $provinces,
            'regencies' => $regenciesQuery->get(['id', 'province_id', 'name']),
            'branches' => $branchesQuery->get(['id', 'code', 'name', 'regency_id', 'province_id']),
            'can_filter_province' => $actor->isAdminNasional(),
            'can_filter_regency' => $actor->isAdminNasional() || $actor->isAdminProvinsi(),
            'can_filter_branch' => ! $actor->isAdminCabang(),
        ];
    }

    /**
     * Aggregate paid transactions per branch using indexed columns (branch_id, payment_status, paid_at).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByBranch(User $actor, array $filters): array
    {
        $branchesQuery = Branch::forUser($actor)
            ->with(['regency:id,name', 'province:id,name'])
            ->orderBy('code');

        if (! empty($filters['province_id'])) {
            $branchesQuery->where('province_id', $filters['province_id']);
        }
        if (! empty($filters['regency_id'])) {
            $branchesQuery->where('regency_id', $filters['regency_id']);
        }
        if (! empty($filters['branch_id'])) {
            $branchesQuery->where('id', (int) $filters['branch_id']);
        }

        $branches = $branchesQuery->get();
        $branchIds = $branches->pluck('id')->all();

        if (empty($branchIds)) {
            return [];
        }

        $aggregates = $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true)
            ->whereIn('transactions.branch_id', $branchIds)
            ->groupBy('transactions.branch_id')
            ->select('transactions.branch_id')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw("SUM(CASE WHEN transactions.payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count")
            ->selectRaw("SUM(CASE WHEN transactions.payment_method = 'midtrans' THEN 1 ELSE 0 END) as midtrans_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.payment_method = 'cash' THEN transactions.total_amount ELSE 0 END), 0) as cash_revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.payment_method = 'midtrans' THEN transactions.total_amount ELSE 0 END), 0) as midtrans_revenue")
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as total_cogs')
            ->selectRaw('COALESCE(SUM(transactions.total_amount - transactions.total_cogs), 0) as gross_profit')
            ->get()
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($aggregates) {
            $agg = $aggregates->get($branch->id);
            $revenue = round((float) ($agg->total_revenue ?? 0), 2);
            $cogs = round((float) ($agg->total_cogs ?? 0), 2);
            $profit = round((float) ($agg->gross_profit ?? 0), 2);
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

            return [
                'branch_id' => $branch->id,
                'branch_code' => $branch->code,
                'branch_name' => $branch->name,
                'regency_name' => $branch->regency?->name,
                'province_name' => $branch->province?->name,
                'total_transactions' => (int) ($agg->total_transactions ?? 0),
                'cash_count' => (int) ($agg->cash_count ?? 0),
                'midtrans_count' => (int) ($agg->midtrans_count ?? 0),
                'cash_revenue' => round((float) ($agg->cash_revenue ?? 0), 2),
                'midtrans_revenue' => round((float) ($agg->midtrans_revenue ?? 0), 2),
                'total_revenue' => $revenue,
                'total_cogs' => $cogs,
                'gross_profit' => $profit,
                'gross_margin_percent' => $margin,
            ];
        })->values()->all();
    }

    /**
     * Build the base Eloquent query for paid transactions scoped via HasRegionScope & indexed paid_at.
     *
     * @param  array<string, mixed>  $filters
     */
    private function paidTransactionsQuery(
        User $actor,
        array $filters,
        bool $applyPaymentMethodFilter = true
    ): Builder {
        [$startAt, $endAt] = $this->resolveDateBounds($filters);

        $query = Transaction::forUser($actor)
            ->where('transactions.payment_status', 'paid')
            ->whereBetween('transactions.paid_at', [$startAt, $endAt]);

        if (! empty($filters['branch_id'])) {
            $query->where('transactions.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('regency_id', $filters['regency_id']));
        } elseif (! empty($filters['province_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('province_id', $filters['province_id']));
        }

        if ($applyPaymentMethodFilter) {
            $paymentMethod = (string) ($filters['payment_method'] ?? 'all');
            if (in_array($paymentMethod, ['cash', 'midtrans'], true)) {
                $query->where('transactions.payment_method', $paymentMethod);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateBounds(array $filters): array
    {
        $startDate = ! empty($filters['start_date'])
            ? (string) $filters['start_date']
            : now()->startOfMonth()->toDateString();

        $endDate = ! empty($filters['end_date'])
            ? (string) $filters['end_date']
            : now()->endOfMonth()->toDateString();

        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ];
    }
}
