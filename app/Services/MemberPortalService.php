<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MemberPortalService
{
    public const PERIOD_CURRENT_YEAR = 'current_year';

    public const PERIOD_CURRENT_MONTH = 'current_month';

    public const PERIOD_LAST_YEAR = 'last_year';

    public const VALID_PERIODS = [
        self::PERIOD_CURRENT_YEAR,
        self::PERIOD_CURRENT_MONTH,
        self::PERIOD_LAST_YEAR,
    ];

    public const SHU_DISCLAIMER = 'Estimasi sementara sebelum penetapan RAT';

    public function __construct(
        private readonly FinancialReportService $financialReportService,
    ) {}

    /**
     * Resolve period identifier to [startAt, endAt, label].
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    public function resolvePeriod(string $period): array
    {
        $normalized = in_array($period, self::VALID_PERIODS, true)
            ? $period
            : self::PERIOD_CURRENT_YEAR;

        return match ($normalized) {
            self::PERIOD_CURRENT_MONTH => [
                now()->startOfMonth(),
                now()->endOfMonth(),
                $normalized,
                'Bulan Ini ('.now()->translatedFormat('F Y').')',
            ],
            self::PERIOD_LAST_YEAR => [
                now()->subYear()->startOfYear(),
                now()->subYear()->endOfYear(),
                $normalized,
                'Tahun Lalu ('.now()->subYear()->year.')',
            ],
            default => [
                now()->startOfYear(),
                now()->endOfYear(),
                self::PERIOD_CURRENT_YEAR,
                'Tahun Berjalan ('.now()->year.')',
            ],
        };
    }

    /**
     * Compute Portal Member dashboard metrics strictly scoped to the given authenticated member:
     * - Net Spending (Total, Home Branch vs Cross-Branch) post-return
     * - Net Member Savings (member_discount_total minus proportional returned item discount)
     * - Estimated Ongoing SHU Jasa Usaha (consistent with FinancialReportService & AppSettingService,
     *   safe against net_profit <= 0 and zero denominator).
     *
     * @return array<string, mixed>
     */
    public function getDashboardSummary(Member $member, string $period = self::PERIOD_CURRENT_YEAR): array
    {
        [$startAt, $endAt, $normalizedPeriod, $periodLabel] = $this->resolvePeriod($period);

        $homeBranchId = (int) $member->home_branch_id;

        // 1. Member paid transactions in period
        $txRow = Transaction::query()
            ->where('member_id', $member->id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startAt, $endAt])
            ->selectRaw('COUNT(*) as paid_transactions_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN branch_id = COALESCE(member_home_branch_id, {$homeBranchId}) THEN total_amount ELSE 0 END), 0) as gross_home_spending")
            ->selectRaw("COALESCE(SUM(CASE WHEN branch_id <> COALESCE(member_home_branch_id, {$homeBranchId}) THEN total_amount ELSE 0 END), 0) as gross_cross_spending")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross_spending_total')
            ->selectRaw('COALESCE(SUM(member_discount_total), 0) as gross_member_discount')
            ->first();

        // 2. Member completed returns in period
        $retRow = TransactionReturn::query()
            ->where('member_id', $member->id)
            ->where('status', TransactionReturn::STATUS_COMPLETED)
            ->whereBetween('returned_at', [$startAt, $endAt])
            ->selectRaw('COUNT(*) as returns_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN branch_id = COALESCE(member_home_branch_id, {$homeBranchId}) THEN total_refund_amount ELSE 0 END), 0) as home_return_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN branch_id <> COALESCE(member_home_branch_id, {$homeBranchId}) THEN total_refund_amount ELSE 0 END), 0) as cross_return_amount")
            ->selectRaw('COALESCE(SUM(total_refund_amount), 0) as total_return_amount')
            ->first();

        $paidCount = (int) ($txRow->paid_transactions_count ?? 0);
        $returnsCount = (int) ($retRow->returns_count ?? 0);

        $grossHome = round((float) ($txRow->gross_home_spending ?? 0), 2);
        $grossCross = round((float) ($txRow->gross_cross_spending ?? 0), 2);
        $grossTotal = round((float) ($txRow->gross_spending_total ?? 0), 2);
        $grossDiscount = round((float) ($txRow->gross_member_discount ?? 0), 2);

        $homeReturn = round((float) ($retRow->home_return_amount ?? 0), 2);
        $crossReturn = round((float) ($retRow->cross_return_amount ?? 0), 2);
        $totalReturn = round((float) ($retRow->total_return_amount ?? 0), 2);

        $homeBranchSpending = round(max(0.0, $grossHome - $homeReturn), 2);
        $crossBranchSpending = round(max(0.0, $grossCross - $crossReturn), 2);
        $netSpendingTotal = round(max(0.0, $grossTotal - $totalReturn), 2);

        // 3. Proportional returned item member discount (ANSI SQL compatible with MySQL & SQLite)
        // Formula (PRD_PHASE3 §6.2): (regular_price_snapshot - unit_price_snapshot) * qty_returned
        $returnedDiscount = (float) DB::table('transaction_return_items')
            ->join('transaction_returns', 'transaction_returns.id', '=', 'transaction_return_items.transaction_return_id')
            ->join('transaction_items', 'transaction_items.id', '=', 'transaction_return_items.transaction_item_id')
            ->where('transaction_returns.member_id', $member->id)
            ->where('transaction_returns.status', TransactionReturn::STATUS_COMPLETED)
            ->whereBetween('transaction_returns.returned_at', [$startAt, $endAt])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(transaction_items.regular_price_snapshot, transaction_items.unit_price_snapshot) > transaction_items.unit_price_snapshot THEN (COALESCE(transaction_items.regular_price_snapshot, transaction_items.unit_price_snapshot) - transaction_items.unit_price_snapshot) * transaction_return_items.qty_returned ELSE 0 END), 0) as returned_discount')
            ->value('returned_discount');

        $returnedDiscount = round($returnedDiscount, 2);
        $netMemberSavings = round(max(0.0, $grossDiscount - $returnedDiscount), 2);

        // 4. Estimated SHU Jasa Usaha Berjalan (consistent with FinancialReportService & AppSettingService)
        $branchFinancials = $this->financialReportService->getBranchFinancialMetricsForPeriod(
            $homeBranchId,
            $startAt,
            $endAt
        );

        $homeBranchNetProfit = (float) $branchFinancials['net_profit'];
        $memberUsahaPercent = (float) $branchFinancials['member_usaha_percent'];
        $memberUsahaPool = $homeBranchNetProfit > 0
            ? (float) $branchFinancials['member_usaha_pool']
            : 0.0;

        $totalHomeBranchMembersNetSpending = $this->computeTotalBranchMembersNetSpending(
            $homeBranchId,
            $startAt,
            $endAt
        );

        if (
            $homeBranchNetProfit <= 0
            || $memberUsahaPool <= 0
            || $totalHomeBranchMembersNetSpending <= 0
            || $netSpendingTotal <= 0
        ) {
            $spendSharePercent = 0.0;
            $estimatedShu = 0.0;
        } else {
            $ratio = $netSpendingTotal / $totalHomeBranchMembersNetSpending;
            $spendSharePercent = round($ratio * 100, 2);
            $estimatedShu = round($memberUsahaPool * $ratio, 2);
        }

        return [
            'period' => $normalizedPeriod,
            'period_label' => $periodLabel,
            'start_date' => $startAt->toDateString(),
            'end_date' => $endAt->toDateString(),
            'paid_transactions_count' => $paidCount,
            'returns_count' => $returnsCount,
            'gross_spending_total' => $grossTotal,
            'total_return_amount' => $totalReturn,
            'net_spending_total' => $netSpendingTotal,
            'home_branch_spending' => $homeBranchSpending,
            'cross_branch_spending' => $crossBranchSpending,
            'gross_member_savings' => $grossDiscount,
            'returned_member_discount' => $returnedDiscount,
            'net_member_savings' => $netMemberSavings,
            'home_branch_net_profit' => $homeBranchNetProfit,
            'shu_member_usaha_percent' => $memberUsahaPercent,
            'home_branch_member_usaha_pool' => $memberUsahaPool,
            'home_branch_total_members_net_spending' => $totalHomeBranchMembersNetSpending,
            'spend_share_percent' => $spendSharePercent,
            'estimated_shu' => $estimatedShu,
            'shu_disclaimer' => self::SHU_DISCLAIMER,
        ];
    }

    /**
     * Compute total net spending (paid - completed returns, floored at 0 per member) across all
     * members registered to the given home_branch_id within [startAt, endAt].
     */
    private function computeTotalBranchMembersNetSpending(int $homeBranchId, Carbon $startAt, Carbon $endAt): float
    {
        $memberIds = Member::query()
            ->where('home_branch_id', $homeBranchId)
            ->pluck('id')
            ->all();

        if (empty($memberIds)) {
            return 0.0;
        }

        $grossByMember = Transaction::query()
            ->whereIn('member_id', $memberIds)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startAt, $endAt])
            ->groupBy('member_id')
            ->select('member_id')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross_spend')
            ->pluck('gross_spend', 'member_id');

        $returnsByMember = TransactionReturn::query()
            ->whereIn('member_id', $memberIds)
            ->where('status', TransactionReturn::STATUS_COMPLETED)
            ->whereBetween('returned_at', [$startAt, $endAt])
            ->groupBy('member_id')
            ->select('member_id')
            ->selectRaw('COALESCE(SUM(total_refund_amount), 0) as return_spend')
            ->pluck('return_spend', 'member_id');

        $totalNet = 0.0;
        foreach ($memberIds as $id) {
            $gross = (float) ($grossByMember[$id] ?? 0.0);
            $ret = (float) ($returnsByMember[$id] ?? 0.0);
            $totalNet += max(0.0, $gross - $ret);
        }

        return round($totalNet, 2);
    }
}
