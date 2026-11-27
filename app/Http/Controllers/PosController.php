<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CashierShiftPosResource;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Services\MidtransReconciliationService;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PosController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly MidtransService $midtransService,
        private readonly MidtransReconciliationService $reconciliationService,
        private readonly CashierShiftService $shiftService,
    ) {}

    /**
     * Render the main POS Cashier screen (GET /pos).
     * Accessible even when pos_locked = true so PosLockModal can overlay without losing client cart state.
     */
    public function index(Request $request): Response
    {
        $cashier = $request->user();

        if (! $cashier || ! $cashier->is_active || ! $cashier->branch_id) {
            abort(403, 'Akun kasir sedang tidak aktif.');
        }

        $branch = $cashier->branch()
            ->with([
                'village:id,name',
                'district:id,name',
                'regency:id,name',
                'province:id,name',
            ])
            ->first();

        if (! $branch || ! $branch->is_active) {
            abort(403, 'Cabang KOPDES sedang tidak aktif.');
        }

        $branchStocksByProduct = BranchStock::where('branch_id', $branch->id)
            ->get()
            ->keyBy('product_id');

        $products = Product::query()
            ->where('is_active', true)
            ->with(['category:id,name'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branchStocksByProduct) {
                /** @var BranchStock|null $stock */
                $stock = $branchStocksByProduct->get($product->id);
                $quantity = (int) ($stock?->quantity ?? 0);
                $reservedQty = (int) ($stock?->reserved_qty ?? 0);
                $availableStock = max(0, $quantity - $reservedQty);

                return [
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'selling_price' => (float) $product->selling_price,
                    'member_price' => $product->member_price !== null ? (float) $product->member_price : null,
                    'quantity' => $quantity,
                    'reserved_qty' => $reservedQty,
                    'available_stock' => $availableStock,
                ];
            });

        $pendingTransactions = Transaction::forUser($cashier)
            ->where('payment_status', 'pending')
            ->with(['items', 'member.homeBranch', 'branch', 'cashier'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Transaction $tx) => $this->formatTransactionForPos($tx));

        $todayTransactions = Transaction::forUser($cashier)
            ->whereDate('created_at', today())
            ->with(['items', 'member.homeBranch', 'branch', 'cashier'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Transaction $tx) => $this->formatTransactionForPos($tx));

        $activeShift = $this->shiftService->getActiveShiftForCashier($cashier);
        $pendingTransactionsForShift = $activeShift
            ? $this->shiftService->getPendingTransactionsForShift($activeShift)
            : [];

        return Inertia::render('Pos/Index', [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'village' => $branch->village,
                'district' => $branch->district,
                'regency' => $branch->regency,
                'province' => $branch->province,
            ],
            'cashier' => [
                'id' => $cashier->id,
                'name' => $cashier->name,
                'card_uid' => $cashier->card_uid,
                'branch_id' => $cashier->branch_id,
            ],
            'activeShift' => $activeShift
                ? (new CashierShiftPosResource($activeShift))->resolve($request)
                : null,
            'pendingTransactionsForShift' => $pendingTransactionsForShift,
            'pendingCountForShift' => count($pendingTransactionsForShift),
            'products' => $products,
            'initialPendingTransactions' => $pendingTransactions,
            'initialTodayTransactions' => $todayTransactions,
            'posLocked' => (bool) $request->session()->get('pos_locked', false),
            'midtrans' => [
                'client_key' => (string) config('services.midtrans.client_key', ''),
                'snap_js_url' => (string) config(
                    'services.midtrans.snap_js_url',
                    'https://app.sandbox.midtrans.com/snap/snap.js'
                ),
                'is_production' => (bool) config('services.midtrans.is_production', false),
            ],
        ]);
    }

    /**
     * Execute an atomic POS checkout for Cash or Midtrans Snap (POST /pos/checkout).
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $transaction = $this->checkoutService->checkout(
            $request->user(),
            $request->validated()
        );

        $formatted = $this->formatTransactionForPos($transaction);

        return response()->json([
            ...$formatted,
            'transaction_id' => $transaction->id,
            'snap_token' => $transaction->snap_token,
            'transaction' => $formatted,
            'message' => $transaction->payment_method === 'cash'
                ? "Transaksi tunai {$transaction->invoice_number} berhasil diselesaikan."
                : "Transaksi Midtrans {$transaction->invoice_number} berhasil dibuat. Silakan selesaikan pembayaran.",
        ]);
    }

    /**
     * List all pending Midtrans transactions for the cashier's branch (GET /pos/transactions/pending).
     */
    public function pendingTransactions(Request $request): JsonResponse
    {
        $cashier = $request->user();

        $transactions = Transaction::forUser($cashier)
            ->where('payment_status', 'pending')
            ->with(['items', 'member.homeBranch', 'branch', 'cashier'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Transaction $tx) => $this->formatTransactionForPos($tx));

        return response()->json([
            'transactions' => $transactions,
            'data' => $transactions,
        ]);
    }

    /**
     * List today's transactions for the cashier's branch for receipt reprinting (GET /pos/transactions/today).
     */
    public function todayTransactions(Request $request): JsonResponse
    {
        $cashier = $request->user();

        $transactions = Transaction::forUser($cashier)
            ->whereDate('created_at', today())
            ->with(['items', 'member.homeBranch', 'branch', 'cashier'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Transaction $tx) => $this->formatTransactionForPos($tx));

        return response()->json([
            'transactions' => $transactions,
            'data' => $transactions,
        ]);
    }

    /**
     * Manually check payment status of a pending Midtrans transaction via Midtrans Status API
     * and reconcile via the single MidtransReconciliationService (POST /pos/transactions/{transaction}/check-status).
     */
    public function checkStatus(Request $request, Transaction $transaction): JsonResponse
    {
        $cashier = $request->user();

        if ((int) $transaction->branch_id !== (int) $cashier->branch_id) {
            abort(403, 'Anda tidak berhak mengakses transaksi milik cabang KOPDES lain.');
        }

        if ($transaction->payment_method !== 'midtrans') {
            $formatted = $this->formatTransactionForPos($transaction->load(['items', 'member.homeBranch', 'branch', 'cashier']));

            return response()->json([
                'message' => 'Transaksi ini bukan menggunakan metode pembayaran Midtrans.',
                'transaction' => $formatted,
            ], 422);
        }

        if ($transaction->payment_status !== 'pending') {
            $formatted = $this->formatTransactionForPos($transaction->load(['items', 'member.homeBranch', 'branch', 'cashier']));

            return response()->json([
                ...$formatted,
                'transaction' => $formatted,
                'message' => "Status transaksi sudah final ({$transaction->payment_status}).",
            ]);
        }

        $statusPayload = $this->midtransService->getTransactionStatus($transaction->invoice_number);
        $statusPayload['order_id'] = $statusPayload['order_id'] ?? $transaction->invoice_number;
        $statusPayload['gross_amount'] = $statusPayload['gross_amount'] ?? (string) $transaction->total_amount;

        $reconciled = $this->reconciliationService->applyStatus(
            $statusPayload,
            'manual_check',
            true
        ) ?? $transaction->refresh()->load(['items', 'member.homeBranch', 'branch', 'cashier']);

        $formatted = $this->formatTransactionForPos($reconciled);

        return response()->json([
            ...$formatted,
            'transaction' => $formatted,
            'message' => "Status pembayaran transaksi {$reconciled->invoice_number}: ".strtoupper($reconciled->payment_status),
        ]);
    }

    /**
     * Cancel a pending Midtrans transaction, invoke Midtrans Cancel API, and release reserved_qty
     * via MidtransReconciliationService (POST /pos/transactions/{transaction}/cancel).
     */
    public function cancel(Request $request, Transaction $transaction): JsonResponse
    {
        $cashier = $request->user();

        if ((int) $transaction->branch_id !== (int) $cashier->branch_id) {
            abort(403, 'Anda tidak berhak membatalkan transaksi milik cabang KOPDES lain.');
        }

        if ($transaction->payment_status !== 'pending') {
            $formatted = $this->formatTransactionForPos($transaction->load(['items', 'member.homeBranch', 'branch', 'cashier']));

            return response()->json([
                'message' => 'Hanya transaksi berstatus pending yang dapat dibatalkan.',
                'transaction' => $formatted,
            ], 422);
        }

        $cancelPayload = [
            'order_id' => $transaction->invoice_number,
            'transaction_status' => 'cancel',
            'gross_amount' => (string) $transaction->total_amount,
        ];

        try {
            $apiResponse = $this->midtransService->cancelTransaction($transaction->invoice_number);

            if (! empty($apiResponse)) {
                $remoteStatus = strtolower((string) ($apiResponse['transaction_status'] ?? 'cancel'));
                $cancelPayload = array_merge($apiResponse, [
                    'order_id' => $transaction->invoice_number,
                    'transaction_status' => in_array($remoteStatus, ['settlement', 'capture'], true) ? $remoteStatus : 'cancel',
                    'gross_amount' => $apiResponse['gross_amount'] ?? (string) $transaction->total_amount,
                ]);
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 404) {
                // SEC-07: Do NOT blindly cancel locally on 412/5xx/timeout; verify real status at Midtrans first.
                try {
                    $statusResponse = $this->midtransService->getTransactionStatus($transaction->invoice_number);
                    $statusCode = (string) ($statusResponse['status_code'] ?? '');
                    $remoteStatus = strtolower(trim((string) ($statusResponse['transaction_status'] ?? '')));

                    if ($statusCode !== '404') {
                        if (in_array($remoteStatus, ['settlement', 'capture', 'expire', 'cancel', 'deny'], true)) {
                            $statusResponse['order_id'] = $statusResponse['order_id'] ?? $transaction->invoice_number;
                            $statusResponse['gross_amount'] = $statusResponse['gross_amount'] ?? (string) $transaction->total_amount;

                            $reconciled = $this->reconciliationService->applyStatus(
                                $statusResponse,
                                'manual_check',
                                true
                            ) ?? $transaction->refresh()->load(['items', 'member.homeBranch', 'branch', 'cashier']);

                            $formatted = $this->formatTransactionForPos($reconciled);

                            return response()->json([
                                ...$formatted,
                                'transaction' => $formatted,
                                'message' => $reconciled->payment_status === 'paid'
                                    ? "Pembatalan dibatalkan: transaksi {$reconciled->invoice_number} sudah lunas di Midtrans dan otomatis direkonsiliasi."
                                    : "Status transaksi {$reconciled->invoice_number} diperbarui dari Midtrans: ".strtoupper($reconciled->payment_status).'.',
                            ]);
                        }

                        return response()->json([
                            'message' => 'Midtrans menolak pembatalan transaksi. Silakan periksa status pembayaran kembali.',
                        ], 502);
                    }
                } catch (HttpException $statusEx) {
                    if ($statusEx->getStatusCode() !== 404) {
                        return response()->json([
                            'message' => 'Gagal memverifikasi status pembayaran ke Midtrans. Transaksi tetap berstatus pending.',
                        ], 502);
                    }
                } catch (Throwable) {
                    return response()->json([
                        'message' => 'Gagal menghubungi Midtrans untuk membatalkan transaksi. Status tetap pending agar pembayaran tidak hilang.',
                    ], 502);
                }
            }
            // HTTP 404 means Midtrans has not created a core record yet (e.g. Snap popup closed before channel selection),
            // so proceeding with local cancellation & reserved_qty release is safe.
        } catch (Throwable) {
            return response()->json([
                'message' => 'Gagal menghubungi Midtrans untuk membatalkan transaksi. Status tetap pending.',
            ], 502);
        }

        $reconciled = $this->reconciliationService->applyStatus(
            $cancelPayload,
            'manual_check',
            true
        ) ?? $transaction->refresh()->load(['items', 'member.homeBranch', 'branch', 'cashier']);

        $formatted = $this->formatTransactionForPos($reconciled);

        return response()->json([
            ...$formatted,
            'transaction' => $formatted,
            'message' => "Transaksi {$reconciled->invoice_number} berhasil dibatalkan dan stok reservasi telah dilepaskan.",
        ]);
    }

    /**
     * Format a Transaction model into a complete array suitable for POS UI and ReceiptThermal.vue.
     * Excludes internal cost/HPP fields and shift IDs (SEC-05 & SEC-06).
     *
     * @return array<string, mixed>
     */
    private function formatTransactionForPos(Transaction $transaction): array
    {
        $transaction->loadMissing(['items', 'member.homeBranch', 'branch', 'cashier', 'memberHomeBranch']);

        return [
            'id' => $transaction->id,
            'invoice_number' => $transaction->invoice_number,
            'idempotency_key' => $transaction->idempotency_key,
            'branch_id' => $transaction->branch_id,
            'cashier_id' => $transaction->cashier_id,
            'member_id' => $transaction->member_id,
            'member_home_branch_id' => $transaction->member_home_branch_id,
            'regular_subtotal' => (float) $transaction->regular_subtotal,
            'member_discount_total' => (float) $transaction->member_discount_total,
            'subtotal' => (float) $transaction->subtotal,
            'total_amount' => (float) $transaction->total_amount,
            'payment_method' => $transaction->payment_method,
            'payment_status' => $transaction->payment_status,
            'cash_received' => $transaction->cash_received !== null ? (float) $transaction->cash_received : null,
            'change_amount' => $transaction->change_amount !== null ? (float) $transaction->change_amount : null,
            'snap_token' => $transaction->snap_token,
            'midtrans_payment_type' => $transaction->midtrans_payment_type,
            'expires_at' => $transaction->expires_at?->toISOString(),
            'paid_at' => $transaction->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $transaction->created_at?->format('Y-m-d H:i:s'),
            'branch' => $transaction->branch ? [
                'id' => $transaction->branch->id,
                'code' => $transaction->branch->code,
                'name' => $transaction->branch->name,
                'address' => $transaction->branch->address,
                'phone' => $transaction->branch->phone,
            ] : null,
            'cashier' => $transaction->cashier ? [
                'id' => $transaction->cashier->id,
                'name' => $transaction->cashier->name,
            ] : null,
            'member' => $transaction->member ? [
                'id' => $transaction->member->id,
                'member_code' => $transaction->member->member_code,
                'name' => $transaction->member->name,
                'home_branch_id' => $transaction->member->home_branch_id,
                'home_branch_name' => $transaction->member->homeBranch?->name
                    ?? $transaction->memberHomeBranch?->name,
            ] : null,
            'items' => $transaction->items->map(fn (TransactionItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name_snapshot' => $item->product_name_snapshot,
                'barcode_snapshot' => $item->barcode_snapshot,
                'unit_snapshot' => $item->unit_snapshot,
                'qty' => (int) $item->qty,
                'regular_price_snapshot' => (float) $item->regular_price_snapshot,
                'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                'member_discount_snapshot' => (float) $item->member_discount_snapshot,
                'is_member_price_applied' => (bool) $item->is_member_price_applied,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
        ];
    }
}
