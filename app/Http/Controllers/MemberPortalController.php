<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberPinRequest;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Services\MemberPortalService;
use App\Support\DemoAccountGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class MemberPortalController extends Controller
{
    public function __construct(
        private readonly MemberPortalService $memberPortalService,
    ) {}

    /**
     * Mobile-first Portal Member dashboard: Digital Member Card + QR, KPIs, and recent transactions (FR-P3-10).
     */
    public function dashboard(Request $request): Response
    {
        /** @var Member $member */
        $member = $request->user('member');
        $member->loadMissing('homeBranch:id,code,name');

        $period = (string) $request->query('period', MemberPortalService::PERIOD_CURRENT_YEAR);
        $summary = $this->memberPortalService->getDashboardSummary($member, $period);

        $recentTransactions = Transaction::query()
            ->where('member_id', $member->id)
            ->where('payment_status', 'paid')
            ->with(['branch:id,code,name'])
            ->withCount('items')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $tx) => $this->formatTransactionRow($tx, $member))
            ->values()
            ->all();

        return Inertia::render('Member/Dashboard', [
            'summary' => $summary,
            'recentTransactions' => $recentTransactions,
            'periodOptions' => [
                ['value' => MemberPortalService::PERIOD_CURRENT_YEAR, 'label' => 'Tahun Ini'],
                ['value' => MemberPortalService::PERIOD_CURRENT_MONTH, 'label' => 'Bulan Ini'],
                ['value' => MemberPortalService::PERIOD_LAST_YEAR, 'label' => 'Tahun Lalu'],
            ],
        ]);
    }

    /**
     * Paginated list of the authenticated member's own paid transactions across branches (FR-P3-11).
     */
    public function transactions(Request $request): Response
    {
        /** @var Member $member */
        $member = $request->user('member');
        $scope = (string) $request->query('scope', 'all');
        $homeBranchId = (int) $member->home_branch_id;

        $query = Transaction::query()
            ->where('member_id', $member->id)
            ->where('payment_status', 'paid')
            ->with(['branch:id,code,name'])
            ->withCount('items')
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if ($scope === 'home') {
            $query->whereRaw('branch_id = COALESCE(member_home_branch_id, ?)', [$homeBranchId]);
        } elseif ($scope === 'cross') {
            $query->whereRaw('branch_id <> COALESCE(member_home_branch_id, ?)', [$homeBranchId]);
        } else {
            $scope = 'all';
        }

        $paginator = $query->paginate(12)->withQueryString();
        $paginator->through(fn (Transaction $tx) => $this->formatTransactionRow($tx, $member));

        return Inertia::render('Member/Transactions', [
            'transactions' => $paginator,
            'filters' => [
                'scope' => $scope,
            ],
        ]);
    }

    /**
     * Digital receipt view for a single transaction owned strictly by auth('member')->id() (Anti-IDOR, FR-P3-11).
     */
    public function showTransaction(Request $request, Transaction $transaction): Response
    {
        /** @var Member $member */
        $member = $request->user('member');

        // Strict Anti-IDOR guard: forbid access to another member's or non-member or unpaid transaction
        if (
            (int) $transaction->member_id !== (int) $member->id
            || $transaction->payment_status !== 'paid'
        ) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        $transaction->load([
            'branch:id,code,name,address,phone',
            'cashier:id,name',
            'memberHomeBranch:id,code,name',
            'items',
            'returns' => fn ($q) => $q->where('status', TransactionReturn::STATUS_COMPLETED)->orderByDesc('returned_at'),
            'returns.items',
        ]);

        $homeBranchId = (int) ($transaction->member_home_branch_id ?? $member->home_branch_id);
        $isCrossBranch = (int) $transaction->branch_id !== $homeBranchId;

        return Inertia::render('Member/TransactionShow', [
            'transaction' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'paid_at' => $transaction->paid_at?->format('Y-m-d H:i:s'),
                'payment_method' => $transaction->payment_method,
                'midtrans_payment_type' => $transaction->midtrans_payment_type,
                'return_status' => $transaction->return_status ?? 'none',
                'is_cross_branch' => $isCrossBranch,
                'branch' => $transaction->branch ? [
                    'id' => $transaction->branch->id,
                    'code' => $transaction->branch->code,
                    'name' => $transaction->branch->name,
                    'address' => $transaction->branch->address,
                    'phone' => $transaction->branch->phone,
                ] : null,
                'cashier_name' => $transaction->cashier?->name ?? '-',
                'regular_subtotal' => round((float) ($transaction->regular_subtotal ?? $transaction->total_amount), 2),
                'member_discount_total' => round((float) ($transaction->member_discount_total ?? 0), 2),
                'total_amount' => round((float) $transaction->total_amount, 2),
                'cash_received' => $transaction->cash_received !== null ? round((float) $transaction->cash_received, 2) : null,
                'change_amount' => $transaction->change_amount !== null ? round((float) $transaction->change_amount, 2) : null,
                'items' => $transaction->items->map(fn (TransactionItem $item) => [
                    'id' => $item->id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'unit_snapshot' => $item->unit_snapshot,
                    'qty' => (int) $item->qty,
                    'regular_price_snapshot' => round((float) ($item->regular_price_snapshot ?? $item->unit_price_snapshot), 2),
                    'unit_price_snapshot' => round((float) $item->unit_price_snapshot, 2),
                    'member_discount_snapshot' => round((float) ($item->member_discount_snapshot ?? 0), 2),
                    'is_member_price_applied' => (bool) ($item->is_member_price_applied ?? false),
                    'subtotal' => round((float) $item->subtotal, 2),
                ])->values()->all(),
                'returns' => $transaction->returns->map(fn (TransactionReturn $ret) => [
                    'id' => $ret->id,
                    'return_number' => $ret->return_number,
                    'return_type' => $ret->return_type,
                    'refund_method' => $ret->refund_method,
                    'total_refund_amount' => round((float) $ret->total_refund_amount, 2),
                    'reason' => $ret->reason,
                    'returned_at' => $ret->returned_at?->format('Y-m-d H:i:s'),
                    'items' => $ret->items->map(fn (TransactionReturnItem $ri) => [
                        'id' => $ri->id,
                        'product_name_snapshot' => $ri->product_name_snapshot,
                        'qty_returned' => (int) $ri->qty_returned,
                        'condition' => $ri->condition,
                        'unit_price_snapshot' => round((float) $ri->unit_price_snapshot, 2),
                        'subtotal_refund' => round((float) $ri->subtotal_refund, 2),
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Render the member self-service security (PIN change) page (FR-P3-12).
     */
    public function security(): Response
    {
        return Inertia::render('Member/Security');
    }

    /**
     * Update the authenticated member's own 6-digit PIN without logging out their active session (FR-P3-12).
     */
    public function updatePin(UpdateMemberPinRequest $request): RedirectResponse
    {
        /** @var Member $member */
        $member = $request->user('member');

        if (DemoAccountGuard::isProtectedDemoMember($member)) {
            abort(403, 'PIN akun member demo bawaan dilindungi dan tidak dapat diubah saat Mode Demo aktif.');
        }

        $member->update([
            'pin_hash' => Hash::make((string) $request->validated('new_pin')),
        ]);

        return redirect()
            ->back()
            ->with('success', 'PIN Portal Member Anda berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTransactionRow(Transaction $tx, Member $member): array
    {
        $homeBranchId = (int) ($tx->member_home_branch_id ?? $member->home_branch_id);

        return [
            'id' => $tx->id,
            'invoice_number' => $tx->invoice_number,
            'paid_at' => $tx->paid_at?->format('Y-m-d H:i:s'),
            'payment_method' => $tx->payment_method,
            'midtrans_payment_type' => $tx->midtrans_payment_type,
            'return_status' => $tx->return_status ?? 'none',
            'is_cross_branch' => (int) $tx->branch_id !== $homeBranchId,
            'branch_name' => $tx->branch?->name ?? '-',
            'branch_code' => $tx->branch?->code ?? '',
            'items_count' => (int) ($tx->items_count ?? 0),
            'total_amount' => round((float) $tx->total_amount, 2),
            'member_discount_total' => round((float) ($tx->member_discount_total ?? 0), 2),
        ];
    }
}
