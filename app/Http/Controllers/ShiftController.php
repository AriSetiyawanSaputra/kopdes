<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForceCloseShiftRequest;
use App\Http\Requests\ShiftFilterRequest;
use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use App\Models\Transaction;
use App\Services\CashierShiftService;
use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShiftController extends Controller
{
    public function __construct(
        private readonly CashierShiftService $shiftService,
        private readonly FinancialReportService $reportService,
        private readonly ReportExportService $exportService,
    ) {}

    /**
     * Tampilkan riwayat & monitoring shift kasir pada Dashboard Admin (GET /shifts).
     */
    public function index(ShiftFilterRequest $request): Response
    {
        Gate::authorize('viewAny', CashierShift::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $report = $this->shiftService->getShiftsReport($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        return Inertia::render('Shifts/Index', [
            'summary' => $report['summary'],
            'shifts' => $report['shifts'],
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }

    /**
     * Ekspor laporan riwayat shift kasir ke PDF atau Excel (GET /shifts/export/{format}).
     */
    public function export(ShiftFilterRequest $request, string $format): HttpResponse|StreamedResponse
    {
        Gate::authorize('viewAny', CashierShift::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();
        $report = $this->shiftService->getShiftsReport($actor, $filters, limit: null);

        return match ($format) {
            'pdf' => $this->exportService->exportShiftsPdf($actor, $report, $filters),
            'excel' => $this->exportService->exportShiftsExcel($actor, $report, $filters),
            default => abort(404),
        };
    }

    /**
     * Tampilkan rincian shift kasir, mutasi kas laci, daftar transaksi, dan kontrol force-close (GET /shifts/{shift}).
     */
    public function show(Request $request, CashierShift $shift): Response
    {
        Gate::authorize('view', $shift);

        $actor = $request->user();

        $shift->load([
            'branch:id,code,name,regency_id,province_id',
            'branch.regency:id,name',
            'cashier:id,name,card_uid',
            'closedBy:id,name,role',
            'cashMovements.user:id,name',
            'transactions' => fn ($q) => $q->with(['member:id,member_code,name'])->orderByDesc('created_at'),
        ]);

        $pendingTransactions = $shift->isOpen()
            ? $this->shiftService->getPendingTransactionsForShift($shift)
            : [];

        $canForceClose = $shift->isOpen() && $actor->can('forceClose', $shift);

        return Inertia::render('Shifts/Show', [
            'shift' => [
                'id' => $shift->id,
                'shift_code' => $shift->shift_code,
                'status' => $shift->status,
                'is_force_closed' => (bool) $shift->is_force_closed,
                'branch' => $shift->branch ? [
                    'id' => $shift->branch->id,
                    'code' => $shift->branch->code,
                    'name' => $shift->branch->name,
                    'regency_name' => $shift->branch->regency?->name,
                ] : null,
                'cashier' => $shift->cashier ? [
                    'id' => $shift->cashier->id,
                    'name' => $shift->cashier->name,
                ] : null,
                'closed_by' => $shift->closedBy ? [
                    'id' => $shift->closedBy->id,
                    'name' => $shift->closedBy->name,
                ] : null,
                'opening_cash' => (float) $shift->opening_cash,
                'cash_sales_total' => (float) $shift->cash_sales_total,
                'midtrans_sales_total' => (float) $shift->midtrans_sales_total,
                'cash_in_total' => (float) $shift->cash_in_total,
                'cash_out_total' => (float) $shift->cash_out_total,
                'cash_refund_total' => (float) $shift->cash_refund_total,
                'expected_closing_cash' => $shift->expected_closing_cash !== null ? (float) $shift->expected_closing_cash : null,
                'actual_closing_cash' => $shift->actual_closing_cash !== null ? (float) $shift->actual_closing_cash : null,
                'variance' => $shift->variance !== null ? (float) $shift->variance : null,
                'opening_notes' => $shift->opening_notes,
                'closing_notes' => $shift->closing_notes,
                'opened_at' => $shift->opened_at?->format('Y-m-d H:i:s'),
                'closed_at' => $shift->closed_at?->format('Y-m-d H:i:s'),
                'cash_movements' => $shift->cashMovements->sortByDesc('created_at')->values()->map(
                    fn (ShiftCashMovement $m) => [
                        'id' => $m->id,
                        'type' => $m->type,
                        'amount' => (float) $m->amount,
                        'notes' => $m->notes,
                        'user_name' => $m->user?->name ?? '-',
                        'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
                    ]
                )->all(),
                'transactions' => $shift->transactions->map(fn (Transaction $tx) => [
                    'id' => $tx->id,
                    'invoice_number' => $tx->invoice_number,
                    'payment_method' => $tx->payment_method,
                    'payment_status' => $tx->payment_status,
                    'total_amount' => (float) $tx->total_amount,
                    'member_discount_total' => (float) $tx->member_discount_total,
                    'member_name' => $tx->member?->name ?? 'Pembeli Umum',
                    'paid_at' => $tx->paid_at?->format('Y-m-d H:i:s'),
                    'created_at' => $tx->created_at?->format('Y-m-d H:i:s'),
                ])->values()->all(),
            ],
            'pendingTransactions' => $pendingTransactions,
            'permissions' => [
                'can_force_close' => $canForceClose,
            ],
        ]);
    }

    /**
     * Tutup paksa (force-close) shift yang masih terbuka oleh Admin Cabang / Admin Nasional
     * (POST /shifts/{shift}/force-close).
     */
    public function forceClose(ForceCloseShiftRequest $request, CashierShift $shift): RedirectResponse
    {
        $closedShift = $this->shiftService->closeShift(
            $shift,
            $request->user(),
            $request->validated(),
            isForceClose: true
        );

        return redirect()
            ->route('shifts.show', $closedShift)
            ->with('success', "Shift kasir {$closedShift->shift_code} berhasil ditutup paksa (force-close).");
    }
}
