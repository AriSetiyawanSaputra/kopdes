<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionReturnService
{
    /**
     * Eksekusi Retur Parsial maupun Full Void secara atomik di dalam DB::transaction() + lockForUpdate()
     * (FR-P2-05 s.d. FR-P2-08 & PRD_PHASE2 6.3).
     *
     * @param  array<string, mixed>  $data
     */
    public function processReturn(User $actor, array $data): TransactionReturn
    {
        return DB::transaction(function () use ($actor, $data): TransactionReturn {
            // 1. Kunci baris transaksi asal (anti race condition retur ganda)
            /** @var Transaction $transaction */
            $transaction = Transaction::query()
                ->with(['branch'])
                ->where('id', (int) $data['transaction_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $actor->can('create', [TransactionReturn::class, $transaction])) {
                abort(403, 'Anda tidak berwenang memproses retur untuk transaksi cabang ini.');
            }

            // 2. Validasi kelayakan status pembayaran & retur
            if ($transaction->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Hanya transaksi berstatus LUNAS (paid) yang dapat diretur atau dibatalkan.',
                ]);
            }

            if ($transaction->return_status === 'full') {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Seluruh item pada transaksi ini sudah pernah diretur (Full Void).',
                ]);
            }

            // 3. Validasi batas waktu retur (default 7 hari kalender sejak paid_at)
            $maxDays = (int) config('kopdes.return_max_days', 7);
            if (! $transaction->paid_at || now()->gt($transaction->paid_at->copy()->addDays($maxDays)->endOfDay())) {
                throw ValidationException::withMessages([
                    'transaction_id' => "Transaksi ini telah melewati batas waktu retur {$maxDays} hari kalender sejak pembayaran.",
                ]);
            }

            // 4. Validasi metode refund & lock shift aktif jika cash_source = 'active_shift'
            $refundMethod = (string) $data['refund_method'];
            $cashSource = $refundMethod === TransactionReturn::REFUND_CASH
                ? (string) ($data['cash_source'] ?? '')
                : null;
            $refundShiftId = null;
            $refundReference = $refundMethod === TransactionReturn::REFUND_TRANSFER
                ? trim((string) ($data['refund_reference'] ?? ''))
                : null;

            if ($refundMethod === TransactionReturn::REFUND_CASH && $cashSource === TransactionReturn::CASH_SOURCE_ACTIVE_SHIFT) {
                $shift = CashierShift::query()
                    ->where('id', (int) $data['refund_cashier_shift_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $shift || (int) $shift->branch_id !== (int) $transaction->branch_id) {
                    throw ValidationException::withMessages([
                        'refund_cashier_shift_id' => 'Shift kasir terpilih tidak valid untuk cabang transaksi ini.',
                    ]);
                }

                if (! $shift->isOpen()) {
                    throw ValidationException::withMessages([
                        'refund_cashier_shift_id' => 'Shift kasir terpilih baru saja ditutup. Silakan pilih shift aktif lain atau gunakan Kas Cabang.',
                    ]);
                }

                $refundShiftId = $shift->id;
            }

            // 5. Kunci seluruh baris transaction_items milik transaksi ini
            $txItems = TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Hitung akumulasi kuantitas yang sudah pernah diretur per transaction_item_id
            $alreadyReturnedMap = TransactionReturnItem::query()
                ->join('transaction_returns', 'transaction_returns.id', '=', 'transaction_return_items.transaction_return_id')
                ->where('transaction_returns.transaction_id', $transaction->id)
                ->where('transaction_returns.status', TransactionReturn::STATUS_COMPLETED)
                ->groupBy('transaction_return_items.transaction_item_id')
                ->select('transaction_return_items.transaction_item_id')
                ->selectRaw('COALESCE(SUM(transaction_return_items.qty_returned), 0) as qty_sum')
                ->pluck('qty_sum', 'transaction_item_id');

            // 6. Validasi seluruh item yang diminta retur (anti over-return)
            $requestedQtyByTxItem = [];
            $preparedLines = [];
            $restockQtyByProduct = [];
            $totalRefundAmount = 0.0;
            $totalRestockedCogs = 0.0;
            $totalDamagedCogs = 0.0;

            foreach ($data['items'] as $entry) {
                $txItemId = (int) $entry['transaction_item_id'];
                $qtyReturned = (int) $entry['qty_returned'];
                $condition = (string) $entry['condition'];

                /** @var TransactionItem|null $txItem */
                $txItem = $txItems->get($txItemId);
                if (! $txItem) {
                    throw ValidationException::withMessages([
                        'items' => "Item transaksi #{$txItemId} tidak termasuk dalam invoice {$transaction->invoice_number}.",
                    ]);
                }

                $alreadyReturned = (int) ($alreadyReturnedMap->get($txItemId) ?? 0);
                $accumulatedNewQty = ($requestedQtyByTxItem[$txItemId] ?? 0) + $qtyReturned;
                $remainingAllowed = (int) $txItem->qty - $alreadyReturned;

                if ($accumulatedNewQty > $remainingAllowed) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah retur untuk {$txItem->product_name_snapshot} ({$accumulatedNewQty}) melebihi sisa kuantitas yang dapat diretur ({$remainingAllowed}).",
                    ]);
                }

                $requestedQtyByTxItem[$txItemId] = $accumulatedNewQty;

                // WAJIB menggunakan snapshot transaksi asal (bukan harga terbaru master products)
                $unitPrice = round((float) $txItem->unit_price_snapshot, 2);
                $costPrice = round((float) $txItem->cost_price_snapshot, 2);
                $subtotalRefund = round($unitPrice * $qtyReturned, 2);
                $subtotalCogs = round($costPrice * $qtyReturned, 2);

                $totalRefundAmount = round($totalRefundAmount + $subtotalRefund, 2);

                if ($condition === TransactionReturnItem::CONDITION_RESTOCKABLE) {
                    $totalRestockedCogs = round($totalRestockedCogs + $subtotalCogs, 2);
                    $restockQtyByProduct[$txItem->product_id] = ($restockQtyByProduct[$txItem->product_id] ?? 0) + $qtyReturned;
                } else {
                    $totalDamagedCogs = round($totalDamagedCogs + $subtotalCogs, 2);
                }

                $preparedLines[] = [
                    'transaction_item_id' => $txItem->id,
                    'product_id' => $txItem->product_id,
                    'product_name_snapshot' => $txItem->product_name_snapshot,
                    'qty_returned' => $qtyReturned,
                    'condition' => $condition,
                    'unit_price_snapshot' => $unitPrice,
                    'cost_price_snapshot' => $costPrice,
                    'subtotal_refund' => $subtotalRefund,
                    'subtotal_cogs' => $subtotalCogs,
                ];
            }

            // 7. Tentukan apakah setelah retur ini transaksi menjadi 'full' atau masih 'partial'
            $isFullAfterReturn = true;
            foreach ($txItems as $txItem) {
                $totalReturnedForItem = (int) ($alreadyReturnedMap->get($txItem->id) ?? 0)
                    + (int) ($requestedQtyByTxItem[$txItem->id] ?? 0);

                if ($totalReturnedForItem < (int) $txItem->qty) {
                    $isFullAfterReturn = false;
                    break;
                }
            }

            $returnType = $isFullAfterReturn
                ? TransactionReturn::TYPE_FULL
                : TransactionReturn::TYPE_PARTIAL;

            $returnNumber = $this->generateReturnNumber($transaction->branch);

            // 8. Simpan header & item retur
            $return = TransactionReturn::create([
                'return_number' => $returnNumber,
                'transaction_id' => $transaction->id,
                'branch_id' => $transaction->branch_id,
                'processed_by_user_id' => $actor->id,
                'member_id' => $transaction->member_id,
                'member_home_branch_id' => $transaction->member_home_branch_id,
                'return_type' => $returnType,
                'refund_method' => $refundMethod,
                'cash_source' => $cashSource,
                'refund_cashier_shift_id' => $refundShiftId,
                'refund_reference' => $refundReference,
                'total_refund_amount' => $totalRefundAmount,
                'total_restocked_cogs' => $totalRestockedCogs,
                'total_damaged_cogs' => $totalDamagedCogs,
                'reason' => trim((string) $data['reason']),
                'status' => TransactionReturn::STATUS_COMPLETED,
                'returned_at' => now(),
            ]);

            foreach ($preparedLines as $line) {
                TransactionReturnItem::create(array_merge($line, [
                    'transaction_return_id' => $return->id,
                ]));
            }

            // 9. Untuk item berkondisi 'restockable', urutkan product_id ASC lalu kunci branch_stocks (anti deadlock)
            if (! empty($restockQtyByProduct)) {
                ksort($restockQtyByProduct, SORT_NUMERIC);
                $sortedProductIds = array_keys($restockQtyByProduct);

                $stocks = BranchStock::query()
                    ->where('branch_id', $transaction->branch_id)
                    ->whereIn('product_id', $sortedProductIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                foreach ($restockQtyByProduct as $productId => $qtyToRestock) {
                    /** @var BranchStock|null $stock */
                    $stock = $stocks->get($productId);

                    if ($stock) {
                        $qtyBefore = (int) $stock->quantity;
                        $qtyAfter = $qtyBefore + $qtyToRestock;
                        $stock->update(['quantity' => $qtyAfter]);
                    } else {
                        $qtyBefore = 0;
                        $qtyAfter = $qtyToRestock;
                        BranchStock::create([
                            'branch_id' => $transaction->branch_id,
                            'product_id' => $productId,
                            'quantity' => $qtyAfter,
                            'reserved_qty' => 0,
                            'min_stock' => 5,
                        ]);
                    }

                    StockMovement::create([
                        'branch_id' => $transaction->branch_id,
                        'product_id' => $productId,
                        'user_id' => $actor->id,
                        'type' => 'void_return',
                        'qty_change' => $qtyToRestock,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $qtyAfter,
                        'reference_type' => TransactionReturn::class,
                        'reference_id' => $return->id,
                        'notes' => "Retur Layak Jual ({$return->return_number} / {$transaction->invoice_number})",
                        'created_at' => now(),
                    ]);
                }
            }

            // 10. Perbarui status retur pada transaksi asal
            $transaction->update([
                'return_status' => $isFullAfterReturn ? 'full' : 'partial',
            ]);

            return $return->fresh([
                'transaction',
                'branch',
                'processedBy',
                'member',
                'refundShift.cashier',
                'items',
            ]);
        });
    }

    /**
     * Ambil daftar riwayat retur & ringkasan untuk halaman Dashboard Admin (Returns/Index.vue).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReturnsReport(User $actor, array $filters, ?int $limit = 200): array
    {
        $startAt = Carbon::parse((string) ($filters['start_date'] ?? now()->startOfMonth()->toDateString()))->startOfDay();
        $endAt = Carbon::parse((string) ($filters['end_date'] ?? now()->endOfMonth()->toDateString()))->endOfDay();

        $query = TransactionReturn::forUser($actor)
            ->whereBetween('transaction_returns.returned_at', [$startAt, $endAt]);

        if (! empty($filters['branch_id'])) {
            $query->where('transaction_returns.branch_id', (int) $filters['branch_id']);
        } elseif (! empty($filters['regency_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('regency_id', $filters['regency_id']));
        } elseif (! empty($filters['province_id'])) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('province_id', $filters['province_id']));
        }

        $refundMethod = (string) ($filters['refund_method'] ?? 'all');
        if (in_array($refundMethod, [TransactionReturn::REFUND_CASH, TransactionReturn::REFUND_TRANSFER], true)) {
            $query->where('transaction_returns.refund_method', $refundMethod);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('transaction_returns.return_number', 'like', "%{$search}%")
                    ->orWhere('transaction_returns.reason', 'like', "%{$search}%")
                    ->orWhereHas('transaction', fn (Builder $tq) => $tq->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('branch', function (Builder $bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $summaryRow = (clone $query)
            ->selectRaw('COUNT(*) as total_returns_count')
            ->selectRaw('COALESCE(SUM(total_refund_amount), 0) as total_refund_amount')
            ->selectRaw('COALESCE(SUM(total_restocked_cogs), 0) as total_restocked_cogs')
            ->selectRaw('COALESCE(SUM(total_damaged_cogs), 0) as total_damaged_cogs')
            ->first();

        $returnsQuery = (clone $query)
            ->with([
                'transaction:id,invoice_number,payment_method,total_amount',
                'branch:id,code,name',
                'processedBy:id,name',
                'member:id,member_code,name',
                'refundShift:id,shift_code',
            ])
            ->withCount('items')
            ->orderByDesc('transaction_returns.returned_at')
            ->orderByDesc('transaction_returns.id');

        if ($limit !== null) {
            $returnsQuery->limit($limit);
        }

        $returns = $returnsQuery->get()->map(fn (TransactionReturn $ret) => [
            'id' => $ret->id,
            'return_number' => $ret->return_number,
            'return_type' => $ret->return_type,
            'refund_method' => $ret->refund_method,
            'cash_source' => $ret->cash_source,
            'refund_reference' => $ret->refund_reference,
            'total_refund_amount' => (float) $ret->total_refund_amount,
            'total_restocked_cogs' => (float) $ret->total_restocked_cogs,
            'total_damaged_cogs' => (float) $ret->total_damaged_cogs,
            'reason' => $ret->reason,
            'items_count' => (int) $ret->items_count,
            'returned_at' => $ret->returned_at?->format('Y-m-d H:i:s'),
            'invoice_number' => $ret->transaction?->invoice_number ?? '-',
            'branch' => $ret->branch ? [
                'id' => $ret->branch->id,
                'code' => $ret->branch->code,
                'name' => $ret->branch->name,
            ] : null,
            'processed_by' => $ret->processedBy?->name ?? '-',
            'member_name' => $ret->member?->name ?? 'Pembeli Umum',
            'refund_shift_code' => $ret->refundShift?->shift_code,
        ])->values()->all();

        return [
            'summary' => [
                'total_returns_count' => (int) ($summaryRow->total_returns_count ?? 0),
                'total_refund_amount' => round((float) ($summaryRow->total_refund_amount ?? 0), 2),
                'total_restocked_cogs' => round((float) ($summaryRow->total_restocked_cogs ?? 0), 2),
                'total_damaged_cogs' => round((float) ($summaryRow->total_damaged_cogs ?? 0), 2),
            ],
            'returns' => $returns,
        ];
    }

    /**
     * Generate nomor retur unik: RTN-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}.
     */
    private function generateReturnNumber(Branch $branch): string
    {
        $datePart = now()->format('Ymd');
        $prefix = "RTN-{$branch->code}-{$datePart}-";

        $existingNumbers = TransactionReturn::query()
            ->where('return_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('return_number');

        $maxSeq = 0;
        foreach ($existingNumbers as $num) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $num, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = TransactionReturn::where('return_number', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }
}
