<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnFilterRequest;
use App\Http\Requests\StoreTransactionReturnRequest;
use App\Models\CashierShift;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Services\AppSettingService;
use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use App\Services\TransactionReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionReturnController extends Controller
{
    public function __construct(
        private readonly TransactionReturnService $returnService,
        private readonly FinancialReportService $reportService,
        private readonly ReportExportService $exportService,
        private readonly AppSettingService $appSettingService,
    ) {}

    /**
     * Tampilkan riwayat retur & pembatalan transaksi (GET /returns).
     */
    public function index(ReturnFilterRequest $request): Response
    {
        Gate::authorize('viewAny', TransactionReturn::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();

        $report = $this->returnService->getReturnsReport($actor, $filters);
        $drillDown = $this->reportService->getDrillDownOptions($actor, $filters);

        return Inertia::render('Returns/Index', [
            'summary' => $report['summary'],
            'returns' => $report['returns'],
            'drillDown' => $drillDown,
            'filters' => $filters,
        ]);
    }

    /**
     * Ekspor laporan riwayat retur & pembatalan transaksi ke PDF atau Excel (GET /returns/export/{format}).
     */
    public function export(ReturnFilterRequest $request, string $format): HttpResponse|StreamedResponse
    {
        Gate::authorize('viewAny', TransactionReturn::class);

        $actor = $request->user();
        $filters = $request->resolvedFilters();
        $report = $this->returnService->getReturnsReport($actor, $filters, limit: null);

        return match ($format) {
            'pdf' => $this->exportService->exportReturnsPdf($actor, $report, $filters),
            'excel' => $this->exportService->exportReturnsExcel($actor, $report, $filters),
            default => abort(404),
        };
    }

    /**
     * Tampilkan formulir proses retur parsial / full void untuk 1 transaksi paid
     * (GET /transactions/{transaction}/return).
     */
    public function create(Request $request, Transaction $transaction): Response
    {
        Gate::authorize('create', [TransactionReturn::class, $transaction]);

        $transaction->load([
            'branch:id,code,name',
            'cashier:id,name',
            'member:id,member_code,name',
            'items.returnItems',
        ]);

        $maxDays = (int) $this->appSettingService->get('return_max_days', (int) config('kopdes.return_max_days', 7));
        $isWithinWindow = $transaction->paid_at !== null
            && now()->lte($transaction->paid_at->copy()->addDays($maxDays)->endOfDay());
        $isEligible = $transaction->payment_status === 'paid'
            && $transaction->return_status !== 'full'
            && $isWithinWindow;

        $ineligibleReason = null;
        if ($transaction->payment_status !== 'paid') {
            $ineligibleReason = 'Hanya transaksi berstatus LUNAS (paid) yang dapat diretur.';
        } elseif ($transaction->return_status === 'full') {
            $ineligibleReason = 'Seluruh item pada transaksi ini sudah pernah diretur (Full Void).';
        } elseif (! $isWithinWindow) {
            $ineligibleReason = "Transaksi ini telah melewati batas retur {$maxDays} hari kalender.";
        }

        $openShifts = CashierShift::query()
            ->with('cashier:id,name')
            ->where('branch_id', $transaction->branch_id)
            ->where('status', CashierShift::STATUS_OPEN)
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn (CashierShift $s) => [
                'id' => $s->id,
                'shift_code' => $s->shift_code,
                'cashier_name' => $s->cashier?->name ?? '-',
                'opened_at' => $s->opened_at?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();

        return Inertia::render('Returns/Create', [
            'transaction' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'payment_method' => $transaction->payment_method,
                'payment_status' => $transaction->payment_status,
                'return_status' => $transaction->return_status ?? 'none',
                'total_amount' => (float) $transaction->total_amount,
                'paid_at' => $transaction->paid_at?->format('Y-m-d H:i:s'),
                'branch' => $transaction->branch,
                'cashier' => $transaction->cashier,
                'member' => $transaction->member,
                'items' => $transaction->items->map(fn (TransactionItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'unit_snapshot' => $item->unit_snapshot,
                    'qty' => (int) $item->qty,
                    'returned_qty' => $item->returned_qty,
                    'remaining_returnable_qty' => $item->remaining_returnable_qty,
                    'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                    'cost_price_snapshot' => (float) $item->cost_price_snapshot,
                    'subtotal' => (float) $item->subtotal,
                ])->values()->all(),
            ],
            'openShifts' => $openShifts,
            'eligibility' => [
                'is_eligible' => $isEligible,
                'reason' => $ineligibleReason,
                'max_days' => $maxDays,
            ],
        ]);
    }

    /**
     * Proses dan simpan retur parsial / full void secara atomik (POST /returns).
     */
    public function store(StoreTransactionReturnRequest $request): RedirectResponse
    {
        $return = $this->returnService->processReturn(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('returns.show', $return)
            ->with('success', "Retur {$return->return_number} berhasil diproses.");
    }

    /**
     * Tampilkan detail & bukti retur siap cetak (GET /returns/{transactionReturn}).
     */
    public function show(Request $request, TransactionReturn $transactionReturn): Response
    {
        Gate::authorize('view', $transactionReturn);

        $transactionReturn->load([
            'transaction:id,invoice_number,payment_method,total_amount,paid_at,return_status',
            'branch:id,code,name,address,phone',
            'processedBy:id,name,role',
            'member:id,member_code,name',
            'refundShift:id,shift_code,cashier_id',
            'refundShift.cashier:id,name',
            'items',
        ]);

        return Inertia::render('Returns/Show', [
            'transactionReturn' => [
                'id' => $transactionReturn->id,
                'return_number' => $transactionReturn->return_number,
                'return_type' => $transactionReturn->return_type,
                'refund_method' => $transactionReturn->refund_method,
                'cash_source' => $transactionReturn->cash_source,
                'refund_reference' => $transactionReturn->refund_reference,
                'total_refund_amount' => (float) $transactionReturn->total_refund_amount,
                'total_restocked_cogs' => (float) $transactionReturn->total_restocked_cogs,
                'total_damaged_cogs' => (float) $transactionReturn->total_damaged_cogs,
                'reason' => $transactionReturn->reason,
                'status' => $transactionReturn->status,
                'returned_at' => $transactionReturn->returned_at?->format('Y-m-d H:i:s'),
                'transaction' => $transactionReturn->transaction,
                'branch' => $transactionReturn->branch,
                'processed_by' => $transactionReturn->processedBy,
                'member' => $transactionReturn->member,
                'refund_shift' => $transactionReturn->refundShift ? [
                    'id' => $transactionReturn->refundShift->id,
                    'shift_code' => $transactionReturn->refundShift->shift_code,
                    'cashier_name' => $transactionReturn->refundShift->cashier?->name ?? '-',
                ] : null,
                'items' => $transactionReturn->items->map(fn (TransactionReturnItem $item) => [
                    'id' => $item->id,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'qty_returned' => (int) $item->qty_returned,
                    'condition' => $item->condition,
                    'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                    'cost_price_snapshot' => (float) $item->cost_price_snapshot,
                    'subtotal_refund' => (float) $item->subtotal_refund,
                    'subtotal_cogs' => (float) $item->subtotal_cogs,
                ])->values()->all(),
            ],
        ]);
    }
}
