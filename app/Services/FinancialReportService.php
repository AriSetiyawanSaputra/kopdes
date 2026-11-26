<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportService
{
    public function __construct(
        private readonly AppSettingService $appSettingService,
    ) {}
    /**
     * Compute hierarchical dashboard KPIs with Phase 2 return correction:
     * Omzet Bruto -> (-) Retur -> Omzet Bersih -> (-) HPP Bersih (HPP Bruto - HPP Restock) ->
     * Laba Kotor -> (-) Biaya Operasional -> Laba Bersih / Dasar SHU, plus payment method breakdown
     * and branch summaries strictly from transaction & return snapshots (FR-15, FR-P2-11).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDashboardSummary(User $actor, array $filters): array
    {
        $paidQuery = $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true);
        $returnsQuery = $this->completedReturnsQuery($actor, $filters, applyPaymentMethodFilter: true);

        $kpis = $this->buildFinancialMetrics(
            $paidQuery,
            $returnsQuery,
            $this->sumOperationalExpenses($actor, $filters)
        );

        $paymentRows = (clone $paidQuery)
            ->select('transactions.payment_method')
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as gross_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as gross_cogs')
            ->groupBy('transactions.payment_method')
            ->get()
            ->keyBy('payment_method');

        $returnPaymentRows = (clone $returnsQuery)
            ->join('transactions', 'transactions.id', '=', 'transaction_returns.transaction_id')
            ->select('transactions.payment_method')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_refund_amount), 0) as total_returns')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_restocked_cogs), 0) as restocked_cogs')
            ->groupBy('transactions.payment_method')
            ->get()
            ->keyBy('payment_method');

        $cashRow = $paymentRows->get('cash');
        $cashRet = $returnPaymentRows->get('cash');
        $cashNetRev = round((float) ($cashRow->gross_revenue ?? 0) - (float) ($cashRet->total_returns ?? 0), 2);
        $cashNetCogs = round((float) ($cashRow->gross_cogs ?? 0) - (float) ($cashRet->restocked_cogs ?? 0), 2);

        $midtransRow = $paymentRows->get('midtrans');
        $midtransRet = $returnPaymentRows->get('midtrans');
        $midtransNetRev = round((float) ($midtransRow->gross_revenue ?? 0) - (float) ($midtransRet->total_returns ?? 0), 2);
        $midtransNetCogs = round((float) ($midtransRow->gross_cogs ?? 0) - (float) ($midtransRet->restocked_cogs ?? 0), 2);

        $branchBreakdown = $this->aggregateByBranch($actor, $filters);

        return [
            'kpis' => $kpis,
            'payment_breakdown' => [
                'cash' => [
                    'transactions_count' => (int) ($cashRow->transactions_count ?? 0),
                    'gross_revenue' => round((float) ($cashRow->gross_revenue ?? 0), 2),
                    'total_returns' => round((float) ($cashRet->total_returns ?? 0), 2),
                    'total_revenue' => $cashNetRev,
                    'gross_profit' => round($cashNetRev - $cashNetCogs, 2),
                ],
                'midtrans' => [
                    'transactions_count' => (int) ($midtransRow->transactions_count ?? 0),
                    'gross_revenue' => round((float) ($midtransRow->gross_revenue ?? 0), 2),
                    'total_returns' => round((float) ($midtransRet->total_returns ?? 0), 2),
                    'total_revenue' => $midtransNetRev,
                    'gross_profit' => round($midtransNetRev - $midtransNetCogs, 2),
                ],
            ],
            'branch_summaries' => $branchBreakdown,
        ];
    }

    /**
     * Compute detailed sales & profit/loss report (branch aggregation + paid transaction ledger)
     * strictly using transaction & return snapshots and branch operational expenses (FR-16, FR-P2-11).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesReport(User $actor, array $filters, ?int $transactionLimit = 250): array
    {
        $paidQuery = $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true);
        $returnsQuery = $this->completedReturnsQuery($actor, $filters, applyPaymentMethodFilter: true);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $paidQuery->where(function (Builder $q) use ($search) {
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

            $returnsQuery->where(function (Builder $q) use ($search) {
                $q->where('transaction_returns.return_number', 'like', "%{$search}%")
                    ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('invoice_number', 'like', "%{$search}%"))
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

        $summary = $this->buildFinancialMetrics(
            $paidQuery,
            $returnsQuery,
            $this->sumOperationalExpenses($actor, $filters)
        );

        $branchSummaries = $this->aggregateByBranch($actor, $filters);

        $transactionsQuery = (clone $paidQuery)
            ->with([
                'branch:id,code,name,regency_id,province_id',
                'cashier:id,name',
                'member:id,member_code,name,home_branch_id',
                'memberHomeBranch:id,code,name',
                'items',
            ])
            ->orderByDesc('transactions.paid_at')
            ->orderByDesc('transactions.id');

        if ($transactionLimit !== null) {
            $transactionsQuery->limit($transactionLimit);
        }

        $transactions = $transactionsQuery
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
                    'return_status' => $tx->return_status ?? 'none',
                    'branch_id' => $tx->branch_id,
                    'branch' => $tx->branch,
                    'cashier' => $tx->cashier,
                    'member' => $tx->member ? [
                        'id' => $tx->member->id,
                        'member_code' => $tx->member->member_code,
                        'name' => $tx->member->name,
                        'home_branch_name' => $tx->memberHomeBranch?->name,
                    ] : null,
                    'regular_subtotal' => round((float) ($tx->regular_subtotal ?? $revenue), 2),
                    'member_discount_total' => round((float) ($tx->member_discount_total ?? 0), 2),
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
                        'regular_price_snapshot' => round((float) ($item->regular_price_snapshot ?? $item->unit_price_snapshot), 2),
                        'unit_price_snapshot' => round((float) $item->unit_price_snapshot, 2),
                        'member_discount_snapshot' => round((float) ($item->member_discount_snapshot ?? 0), 2),
                        'is_member_price_applied' => (bool) ($item->is_member_price_applied ?? false),
                        'cost_price_snapshot' => round((float) $item->cost_price_snapshot, 2),
                        'subtotal' => round((float) $item->subtotal, 2),
                        'subtotal_cogs' => round((float) $item->subtotal_cogs, 2),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'branch_summaries' => $branchSummaries,
            'transactions' => $transactions,
        ];
    }

    /**
     * Compute cross-branch member shopping recap & SHU calculation grounded on Post-Return Net Profit:
     * Omzet Bruto -> (-) Total Retur -> Omzet Bersih -> (-) HPP Bersih -> Laba Kotor ->
     * (-) Biaya Operasional -> Laba Bersih / Dasar SHU, with member spending deducted by returns (FR-17, FR-P2-11).
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

        // Hitung dasar SHU dari Laba Bersih pasca-retur periode & cakupan wilayah terpilih
        $shuMetrics = $this->buildFinancialMetrics(
            $this->paidTransactionsQuery($actor, $filters, applyPaymentMethodFilter: true),
            $this->completedReturnsQuery($actor, $filters, applyPaymentMethodFilter: true),
            $this->sumOperationalExpenses($actor, $filters)
        );
        $distributableShuBasis = max(0.0, (float) $shuMetrics['net_profit']);
        $allocPercents = $this->appSettingService->shuAllocations();
        $memberUsahaPool = round($distributableShuBasis * ($allocPercents['member_usaha'] / 100), 2);
        $shuAllocations = [
            'member_usaha' => [
                'percent' => $allocPercents['member_usaha'],
                'amount' => $memberUsahaPool,
            ],
            'reserve' => [
                'percent' => $allocPercents['reserve'],
                'amount' => round($distributableShuBasis * ($allocPercents['reserve'] / 100), 2),
            ],
            'management' => [
                'percent' => $allocPercents['management'],
                'amount' => round($distributableShuBasis * ($allocPercents['management'] / 100), 2),
            ],
            'education' => [
                'percent' => $allocPercents['education'],
                'amount' => round($distributableShuBasis * ($allocPercents['education'] / 100), 2),
            ],
        ];

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
        $returnAggregates = collect();

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
                ->selectRaw('COALESCE(SUM(transactions.member_discount_total), 0) as member_discount_total')
                ->get()
                ->keyBy('member_id');

            $retQuery = TransactionReturn::query()
                ->join('members', 'members.id', '=', 'transaction_returns.member_id')
                ->join('transactions', 'transactions.id', '=', 'transaction_returns.transaction_id')
                ->whereIn('transaction_returns.member_id', $memberIds)
                ->where('transaction_returns.status', TransactionReturn::STATUS_COMPLETED)
                ->whereBetween('transaction_returns.returned_at', [$startAt, $endAt]);

            if (in_array($paymentMethod, ['cash', 'midtrans'], true)) {
                $retQuery->where('transactions.payment_method', $paymentMethod);
            }

            $returnAggregates = $retQuery
                ->groupBy('transaction_returns.member_id')
                ->select('transaction_returns.member_id')
                ->selectRaw('COUNT(transaction_returns.id) as returns_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN transaction_returns.branch_id = COALESCE(transaction_returns.member_home_branch_id, members.home_branch_id) THEN transaction_returns.total_refund_amount ELSE 0 END), 0) as home_branch_return')
                ->selectRaw('COALESCE(SUM(CASE WHEN transaction_returns.branch_id <> COALESCE(transaction_returns.member_home_branch_id, members.home_branch_id) THEN transaction_returns.total_refund_amount ELSE 0 END), 0) as other_branch_return')
                ->selectRaw('COALESCE(SUM(transaction_returns.total_refund_amount), 0) as total_return')
                ->get()
                ->keyBy('member_id');
        }

        $totalTransactions = 0;
        $totalHomeSpend = 0.0;
        $totalOtherSpend = 0.0;
        $totalAccumulatedSpend = 0.0;
        $totalMemberReturns = 0.0;
        $totalMemberSavings = 0.0;
        $activeShoppingMembers = 0;

        $rows = $members->map(function (Member $member) use (
            $txAggregates,
            $returnAggregates,
            &$totalTransactions,
            &$totalHomeSpend,
            &$totalOtherSpend,
            &$totalAccumulatedSpend,
            &$totalMemberReturns,
            &$totalMemberSavings,
            &$activeShoppingMembers
        ) {
            $agg = $txAggregates->get($member->id);
            $ret = $returnAggregates->get($member->id);

            $txCount = (int) ($agg->paid_transactions_count ?? 0);
            $grossHome = round((float) ($agg->home_branch_spend ?? 0), 2);
            $grossOther = round((float) ($agg->other_branch_spend ?? 0), 2);
            $grossTotal = round((float) ($agg->total_spend ?? 0), 2);
            $memberDiscount = round((float) ($agg->member_discount_total ?? 0), 2);

            $homeReturn = round((float) ($ret->home_branch_return ?? 0), 2);
            $otherReturn = round((float) ($ret->other_branch_return ?? 0), 2);
            $totalReturn = round((float) ($ret->total_return ?? 0), 2);

            $homeSpend = round(max(0.0, $grossHome - $homeReturn), 2);
            $otherSpend = round(max(0.0, $grossOther - $otherReturn), 2);
            $totalSpend = round(max(0.0, $grossTotal - $totalReturn), 2);

            if ($txCount > 0) {
                $activeShoppingMembers++;
            }

            $totalTransactions += $txCount;
            $totalHomeSpend = round($totalHomeSpend + $homeSpend, 2);
            $totalOtherSpend = round($totalOtherSpend + $otherSpend, 2);
            $totalAccumulatedSpend = round($totalAccumulatedSpend + $totalSpend, 2);
            $totalMemberReturns = round($totalMemberReturns + $totalReturn, 2);
            $totalMemberSavings = round($totalMemberSavings + $memberDiscount, 2);

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
                'gross_spend' => $grossTotal,
                'returned_spend' => $totalReturn,
                'member_discount_total' => $memberDiscount,
                'home_branch_spend' => $homeSpend,
                'other_branch_spend' => $otherSpend,
                'total_spend' => $totalSpend,
            ];
        })
            ->map(function (array $row) use ($totalAccumulatedSpend, $memberUsahaPool) {
                $sharePercent = $totalAccumulatedSpend > 0
                    ? round(($row['total_spend'] / $totalAccumulatedSpend) * 100, 2)
                    : 0.0;

                $estimatedShuShare = ($totalAccumulatedSpend > 0 && $memberUsahaPool > 0)
                    ? round(($row['total_spend'] / $totalAccumulatedSpend) * $memberUsahaPool, 2)
                    : 0.0;

                return array_merge($row, [
                    'spend_share_percent' => $sharePercent,
                    'estimated_shu_share' => $estimatedShuShare,
                ]);
            })
            ->sortByDesc('total_spend')
            ->values()
            ->all();

        $shuFinancials = [
            'gross_revenue' => $shuMetrics['gross_revenue'],
            'total_returns' => $shuMetrics['total_returns'],
            'net_revenue' => $shuMetrics['net_revenue'],
            'total_revenue' => $shuMetrics['net_revenue'],
            'gross_cogs' => $shuMetrics['gross_cogs'],
            'restocked_cogs' => $shuMetrics['restocked_cogs'],
            'damaged_cogs' => $shuMetrics['damaged_cogs'],
            'net_cogs' => $shuMetrics['net_cogs'],
            'total_cogs' => $shuMetrics['net_cogs'],
            'gross_profit' => $shuMetrics['gross_profit'],
            'total_operational_expenses' => $shuMetrics['total_operational_expenses'],
            'net_profit' => $shuMetrics['net_profit'],
            'net_margin_percentage' => $shuMetrics['net_margin_percentage'],
            'total_member_discount' => $shuMetrics['total_member_discount'],
            'shu_basis' => $distributableShuBasis,
            'member_usaha_pool' => $memberUsahaPool,
            'shu_allocations' => $shuAllocations,
        ];

        return [
            'summary' => array_merge([
                'total_members' => count($rows),
                'active_shopping_members' => $activeShoppingMembers,
                'total_transactions' => $totalTransactions,
                'total_home_branch_spend' => $totalHomeSpend,
                'total_other_branch_spend' => $totalOtherSpend,
                'total_accumulated_spend' => $totalAccumulatedSpend,
                'total_member_returns' => $totalMemberReturns,
                'total_member_savings' => $totalMemberSavings,
            ], $shuFinancials),
            'shu_financials' => $shuFinancials,
            'members' => $rows,
        ];
    }

    /**
     * Compute post-return financial summary metrics and SHU allocation settings for a single branch
     * over an explicit [startAt, endAt] period (used by MemberPortalService for 100% formula consistency).
     *
     * @return array<string, mixed>
     */
    public function getBranchFinancialMetricsForPeriod(int $branchId, Carbon $startAt, Carbon $endAt): array
    {
        $paidQuery = Transaction::query()
            ->where('transactions.branch_id', $branchId)
            ->where('transactions.payment_status', 'paid')
            ->whereBetween('transactions.paid_at', [$startAt, $endAt]);

        $returnsQuery = TransactionReturn::query()
            ->where('transaction_returns.branch_id', $branchId)
            ->where('transaction_returns.status', TransactionReturn::STATUS_COMPLETED)
            ->whereBetween('transaction_returns.returned_at', [$startAt, $endAt]);

        $totalOperationalExpenses = round((float) OperationalExpense::query()
            ->where('operational_expenses.branch_id', $branchId)
            ->whereDate('operational_expenses.expense_date', '>=', $startAt->toDateString())
            ->whereDate('operational_expenses.expense_date', '<=', $endAt->toDateString())
            ->sum('operational_expenses.amount'), 2);

        $metrics = $this->buildFinancialMetrics($paidQuery, $returnsQuery, $totalOperationalExpenses);
        $distributableShuBasis = max(0.0, (float) $metrics['net_profit']);
        $allocPercents = $this->appSettingService->shuAllocations();

        return array_merge($metrics, [
            'shu_basis' => $distributableShuBasis,
            'member_usaha_percent' => $allocPercents['member_usaha'],
            'member_usaha_pool' => round($distributableShuBasis * ($allocPercents['member_usaha'] / 100), 2),
            'shu_allocations' => [
                'member_usaha' => [
                    'percent' => $allocPercents['member_usaha'],
                    'amount' => round($distributableShuBasis * ($allocPercents['member_usaha'] / 100), 2),
                ],
                'reserve' => [
                    'percent' => $allocPercents['reserve'],
                    'amount' => round($distributableShuBasis * ($allocPercents['reserve'] / 100), 2),
                ],
                'management' => [
                    'percent' => $allocPercents['management'],
                    'amount' => round($distributableShuBasis * ($allocPercents['management'] / 100), 2),
                ],
                'education' => [
                    'percent' => $allocPercents['education'],
                    'amount' => round($distributableShuBasis * ($allocPercents['education'] / 100), 2),
                ],
            ],
        ]);
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
     * Compute post-return financial summary metrics from paid transactions, completed returns,
     * and non-deleted operational expenses strictly per PRD_PHASE2.md §6.2.
     *
     * @return array<string, int|float>
     */
    private function buildFinancialMetrics(
        Builder $paidQuery,
        Builder $returnsQuery,
        float $totalOperationalExpenses
    ): array {
        $kpiRow = (clone $paidQuery)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as gross_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as gross_cogs')
            ->selectRaw('COALESCE(SUM(transactions.member_discount_total), 0) as total_member_discount')
            ->first();

        $returnRow = (clone $returnsQuery)
            ->selectRaw('COALESCE(SUM(transaction_returns.total_refund_amount), 0) as total_returns')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_restocked_cogs), 0) as restocked_cogs')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_damaged_cogs), 0) as damaged_cogs')
            ->first();

        $totalTransactions = (int) ($kpiRow->total_transactions ?? 0);
        $grossRevenue = round((float) ($kpiRow->gross_revenue ?? 0), 2);
        $totalReturns = round((float) ($returnRow->total_returns ?? 0), 2);
        $netRevenue = round($grossRevenue - $totalReturns, 2);

        $grossCogs = round((float) ($kpiRow->gross_cogs ?? 0), 2);
        $restockedCogs = round((float) ($returnRow->restocked_cogs ?? 0), 2);
        $damagedCogs = round((float) ($returnRow->damaged_cogs ?? 0), 2);
        // HPP Bersih hanya dikurangi item restockable; item damaged tetap membebani Laba Kotor
        $netCogs = round($grossCogs - $restockedCogs, 2);

        $grossProfit = round($netRevenue - $netCogs, 2);
        $grossMarginPercent = $netRevenue > 0
            ? round(($grossProfit / $netRevenue) * 100, 2)
            : 0.0;

        $netProfit = round($grossProfit - $totalOperationalExpenses, 2);
        $netMarginPercentage = $netRevenue > 0
            ? round(($netProfit / $netRevenue) * 100, 2)
            : 0.0;

        $totalMemberDiscount = round((float) ($kpiRow->total_member_discount ?? 0), 2);

        return [
            'total_transactions' => $totalTransactions,
            'gross_revenue' => $grossRevenue,
            'total_returns' => $totalReturns,
            'net_revenue' => $netRevenue,
            'total_revenue' => $netRevenue,
            'gross_cogs' => $grossCogs,
            'restocked_cogs' => $restockedCogs,
            'damaged_cogs' => $damagedCogs,
            'net_cogs' => $netCogs,
            'total_cogs' => $netCogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'total_operational_expenses' => round($totalOperationalExpenses, 2),
            'net_profit' => $netProfit,
            'net_margin_percentage' => $netMarginPercentage,
            'total_member_discount' => $totalMemberDiscount,
        ];
    }

    /**
     * Aggregate paid transactions, completed returns, and operational expenses per branch.
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
            ->selectRaw('COALESCE(SUM(transactions.total_amount), 0) as gross_revenue')
            ->selectRaw('COALESCE(SUM(transactions.total_cogs), 0) as gross_cogs')
            ->selectRaw('COALESCE(SUM(transactions.member_discount_total), 0) as total_member_discount')
            ->get()
            ->keyBy('branch_id');

        $returnAggregates = $this->completedReturnsQuery($actor, $filters, applyPaymentMethodFilter: true)
            ->join('transactions', 'transactions.id', '=', 'transaction_returns.transaction_id')
            ->whereIn('transaction_returns.branch_id', $branchIds)
            ->groupBy('transaction_returns.branch_id')
            ->select('transaction_returns.branch_id')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_refund_amount), 0) as total_returns')
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.payment_method = 'cash' THEN transaction_returns.total_refund_amount ELSE 0 END), 0) as cash_returns")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.payment_method = 'midtrans' THEN transaction_returns.total_refund_amount ELSE 0 END), 0) as midtrans_returns")
            ->selectRaw('COALESCE(SUM(transaction_returns.total_restocked_cogs), 0) as restocked_cogs')
            ->selectRaw('COALESCE(SUM(transaction_returns.total_damaged_cogs), 0) as damaged_cogs')
            ->get()
            ->keyBy('branch_id');

        $expenseAggregates = $this->operationalExpensesQuery($actor, $filters)
            ->whereIn('operational_expenses.branch_id', $branchIds)
            ->groupBy('operational_expenses.branch_id')
            ->select('operational_expenses.branch_id')
            ->selectRaw('COALESCE(SUM(operational_expenses.amount), 0) as total_operational_expenses')
            ->get()
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($aggregates, $returnAggregates, $expenseAggregates) {
            $agg = $aggregates->get($branch->id);
            $ret = $returnAggregates->get($branch->id);
            $expAgg = $expenseAggregates->get($branch->id);

            $grossRevenue = round((float) ($agg->gross_revenue ?? 0), 2);
            $totalReturns = round((float) ($ret->total_returns ?? 0), 2);
            $netRevenue = round($grossRevenue - $totalReturns, 2);

            $grossCogs = round((float) ($agg->gross_cogs ?? 0), 2);
            $restockedCogs = round((float) ($ret->restocked_cogs ?? 0), 2);
            $damagedCogs = round((float) ($ret->damaged_cogs ?? 0), 2);
            $netCogs = round($grossCogs - $restockedCogs, 2);

            $grossProfit = round($netRevenue - $netCogs, 2);
            $grossMargin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0;

            $operationalExpenses = round((float) ($expAgg->total_operational_expenses ?? 0), 2);
            $netProfit = round($grossProfit - $operationalExpenses, 2);
            $netMargin = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 2) : 0.0;

            $cashNetRevenue = round((float) ($agg->cash_revenue ?? 0) - (float) ($ret->cash_returns ?? 0), 2);
            $midtransNetRevenue = round((float) ($agg->midtrans_revenue ?? 0) - (float) ($ret->midtrans_returns ?? 0), 2);

            return [
                'branch_id' => $branch->id,
                'branch_code' => $branch->code,
                'branch_name' => $branch->name,
                'regency_name' => $branch->regency?->name,
                'province_name' => $branch->province?->name,
                'total_transactions' => (int) ($agg->total_transactions ?? 0),
                'cash_count' => (int) ($agg->cash_count ?? 0),
                'midtrans_count' => (int) ($agg->midtrans_count ?? 0),
                'cash_revenue' => $cashNetRevenue,
                'midtrans_revenue' => $midtransNetRevenue,
                'gross_revenue' => $grossRevenue,
                'total_returns' => $totalReturns,
                'net_revenue' => $netRevenue,
                'total_revenue' => $netRevenue,
                'gross_cogs' => $grossCogs,
                'restocked_cogs' => $restockedCogs,
                'damaged_cogs' => $damagedCogs,
                'net_cogs' => $netCogs,
                'total_cogs' => $netCogs,
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $grossMargin,
                'operational_expenses' => $operationalExpenses,
                'net_profit' => $netProfit,
                'net_margin_percentage' => $netMargin,
                'total_member_discount' => round((float) ($agg->total_member_discount ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * Sum non-deleted operational expenses within date range and region scope.
     * Note: shift_cash_movements are strictly excluded from operational expenses.
     *
     * @param  array<string, mixed>  $filters
     */
    private function sumOperationalExpenses(User $actor, array $filters): float
    {
        return round((float) $this->operationalExpensesQuery($actor, $filters)->sum('operational_expenses.amount'), 2);
    }

    /**
     * Build the base Eloquent query for operational expenses scoped via HasRegionScope & expense_date.
     * Soft-deleted rows are automatically excluded by OperationalExpense's SoftDeletes trait.
     *
     * @param  array<string, mixed>  $filters
     */
    private function operationalExpensesQuery(User $actor, array $filters): Builder
    {
        [$startDate, $endDate] = $this->resolveDateStrings($filters);

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

        return $query;
    }

    /**
     * Build the base Eloquent query for completed transaction returns scoped via HasRegionScope & returned_at.
     *
     * @param  array<string, mixed>  $filters
     */
    private function completedReturnsQuery(
        User $actor,
        array $filters,
        bool $applyPaymentMethodFilter = true
    ): Builder {
        [$startAt, $endAt] = $this->resolveDateBounds($filters);

        $query = TransactionReturn::forUser($actor)
            ->where('transaction_returns.status', TransactionReturn::STATUS_COMPLETED)
            ->whereBetween('transaction_returns.returned_at', [$startAt, $endAt]);

        if (! empty($filters['branch_id'])) {
            $query->where('transaction_returns.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('regency_id', $filters['regency_id']));
        } elseif (! empty($filters['province_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('province_id', $filters['province_id']));
        }

        if ($applyPaymentMethodFilter) {
            $paymentMethod = (string) ($filters['payment_method'] ?? 'all');
            if (in_array($paymentMethod, ['cash', 'midtrans'], true)) {
                $query->whereHas('transaction', fn (Builder $tq) => $tq->where('payment_method', $paymentMethod));
            }
        }

        return $query;
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
     * @return array{0: string, 1: string}
     */
    private function resolveDateStrings(array $filters): array
    {
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse((string) $filters['start_date'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse((string) $filters['end_date'])->toDateString()
            : now()->endOfMonth()->toDateString();

        return [$startDate, $endDate];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateBounds(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateStrings($filters);

        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ];
    }
}
