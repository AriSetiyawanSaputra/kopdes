<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly MidtransService $midtransService,
    ) {}

    /**
     * Process an atomic POS checkout (cash or midtrans) with idempotency protection,
     * deadlock-free ascending product_id row locking, HPP & member home branch snapshots,
     * and stock deduction / reservation.
     *
     * @param  array<string, mixed>  $data
     */
    public function checkout(User $cashier, array $data): Transaction
    {
        if (! $cashier->isCashier() || ! $cashier->is_active || ! $cashier->branch_id) {
            abort(403, 'Hanya kasir aktif yang dapat memproses transaksi POS.');
        }

        $branch = Branch::findOrFail($cashier->branch_id);

        if (! $branch->is_active) {
            abort(403, 'Cabang KOPDES sedang nonaktif dan tidak dapat memproses transaksi.');
        }

        $idempotencyKey = trim((string) $data['idempotency_key']);

        // Layer 1 Idempotency check before acquiring DB locks (PRD 6.3)
        $existingTransaction = Transaction::with(['items', 'member.homeBranch', 'branch', 'cashier'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingTransaction) {
            return $this->validateExistingIdempotentTransaction($existingTransaction, $branch->id, $cashier->id);
        }

        $normalizedItems = collect($data['items'] ?? [])
            ->groupBy(fn (array $item) => (int) $item['product_id'])
            ->map(fn ($group, $productId) => [
                'product_id' => (int) $productId,
                'qty' => (int) $group->sum(fn ($i) => (int) $i['qty']),
            ])
            ->sortBy('product_id')
            ->values();

        if ($normalizedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang belanja minimal harus berisi 1 produk.',
            ]);
        }

        $sortedProductIds = $normalizedItems->pluck('product_id')->all();
        $paymentMethod = (string) $data['payment_method'];

        try {
            return DB::transaction(function () use (
                $cashier,
                $branch,
                $data,
                $idempotencyKey,
                $normalizedItems,
                $sortedProductIds,
                $paymentMethod
            ): Transaction {
                // Re-verify idempotency inside transaction before locking stocks
                $existingInTx = Transaction::with(['items', 'member.homeBranch', 'branch', 'cashier'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingInTx) {
                    return $this->validateExistingIdempotentTransaction($existingInTx, $branch->id, $cashier->id);
                }

                // Lock branch_stocks ordered by product_id ASC to prevent deadlocks (PRD 6.1 & AGENTS 3.2)
                $stocks = BranchStock::where('branch_id', $branch->id)
                    ->whereIn('product_id', $sortedProductIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $products = Product::whereIn('id', $sortedProductIds)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('id');

                $preparedItems = [];
                $subtotal = 0.0;
                $totalCogs = 0.0;

                foreach ($normalizedItems as $item) {
                    $productId = $item['product_id'];
                    $qty = $item['qty'];

                    /** @var Product|null $product */
                    $product = $products->get($productId);
                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => "Produk dengan ID #{$productId} tidak ditemukan atau sedang tidak aktif.",
                        ]);
                    }

                    /** @var BranchStock|null $stock */
                    $stock = $stocks->get($productId);
                    $availableStock = $stock ? ((int) $stock->quantity - (int) $stock->reserved_qty) : 0;

                    if (! $stock || $availableStock < $qty) {
                        throw ValidationException::withMessages([
                            'items' => "Stok tersedia untuk produk {$product->name} tidak mencukupi (Tersedia: {$availableStock} {$product->unit}, Diminta: {$qty} {$product->unit}).",
                        ]);
                    }

                    $unitPrice = round((float) $product->selling_price, 2);
                    $costPrice = round((float) $product->cost_price, 2);
                    $itemSubtotal = round($unitPrice * $qty, 2);
                    $itemSubtotalCogs = round($costPrice * $qty, 2);

                    $subtotal = round($subtotal + $itemSubtotal, 2);
                    $totalCogs = round($totalCogs + $itemSubtotalCogs, 2);

                    $preparedItems[] = [
                        'stock' => $stock,
                        'product_id' => $product->id,
                        'product_name_snapshot' => $product->name,
                        'barcode_snapshot' => $product->barcode,
                        'unit_snapshot' => $product->unit,
                        'qty' => $qty,
                        'unit_price_snapshot' => $unitPrice,
                        'cost_price_snapshot' => $costPrice,
                        'subtotal' => $itemSubtotal,
                        'subtotal_cogs' => $itemSubtotalCogs,
                    ];
                }

                $totalAmount = $subtotal;

                // Resolve member & snapshot member_home_branch_id (PRD 5.3)
                $memberId = ! empty($data['member_id']) ? (int) $data['member_id'] : null;
                $memberHomeBranchId = null;

                if ($memberId !== null) {
                    $member = Member::where('id', $memberId)
                        ->where('status', 'active')
                        ->first();

                    if (! $member) {
                        throw ValidationException::withMessages([
                            'member_id' => 'Kartu member tidak ditemukan atau sedang tidak aktif.',
                        ]);
                    }

                    $memberHomeBranchId = $member->home_branch_id;
                }

                $isCash = $paymentMethod === 'cash';
                $cashReceived = null;
                $changeAmount = null;

                if ($isCash) {
                    $cashReceived = round((float) ($data['cash_received'] ?? 0), 2);

                    if ($cashReceived < $totalAmount) {
                        throw ValidationException::withMessages([
                            'cash_received' => 'Nominal uang tunai yang diterima kurang dari total belanja.',
                        ]);
                    }

                    $changeAmount = round($cashReceived - $totalAmount, 2);
                }

                $invoiceNumber = $this->generateInvoiceNumber($branch);

                $transaction = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'idempotency_key' => $idempotencyKey,
                    'branch_id' => $branch->id,
                    'cashier_id' => $cashier->id,
                    'member_id' => $memberId,
                    'member_home_branch_id' => $memberHomeBranchId,
                    'subtotal' => $subtotal,
                    'total_amount' => $totalAmount,
                    'total_cogs' => $totalCogs,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $isCash ? 'paid' : 'pending',
                    'cash_received' => $cashReceived,
                    'change_amount' => $changeAmount,
                    'snap_token' => null,
                    'midtrans_payment_type' => null,
                    'expires_at' => $isCash ? null : now()->addMinutes(15),
                    'paid_at' => $isCash ? now() : null,
                ]);

                foreach ($preparedItems as $prepared) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $prepared['product_id'],
                        'product_name_snapshot' => $prepared['product_name_snapshot'],
                        'barcode_snapshot' => $prepared['barcode_snapshot'],
                        'unit_snapshot' => $prepared['unit_snapshot'],
                        'qty' => $prepared['qty'],
                        'unit_price_snapshot' => $prepared['unit_price_snapshot'],
                        'cost_price_snapshot' => $prepared['cost_price_snapshot'],
                        'subtotal' => $prepared['subtotal'],
                        'subtotal_cogs' => $prepared['subtotal_cogs'],
                    ]);
                }

                if ($isCash) {
                    foreach ($preparedItems as $prepared) {
                        /** @var BranchStock $stock */
                        $stock = $prepared['stock'];
                        $qty = $prepared['qty'];
                        $qtyBefore = (int) $stock->quantity;
                        $qtyAfter = $qtyBefore - $qty;

                        $stock->update([
                            'quantity' => $qtyAfter,
                        ]);

                        StockMovement::create([
                            'branch_id' => $branch->id,
                            'product_id' => $prepared['product_id'],
                            'user_id' => $cashier->id,
                            'type' => 'sale',
                            'qty_change' => -$qty,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'reference_type' => Transaction::class,
                            'reference_id' => $transaction->id,
                            'notes' => "Penjualan Tunai POS ({$transaction->invoice_number})",
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    foreach ($preparedItems as $prepared) {
                        /** @var BranchStock $stock */
                        $stock = $prepared['stock'];
                        $qty = $prepared['qty'];

                        $stock->update([
                            'reserved_qty' => (int) $stock->reserved_qty + $qty,
                        ]);
                    }

                    // Request Snap Token inside DB::transaction(); any HTTP 502 exception rolls back reserved_qty & transaction
                    $transaction->load(['items', 'member', 'branch', 'cashier']);
                    $snapToken = $this->midtransService->createSnapToken($transaction);

                    $transaction->update([
                        'snap_token' => $snapToken,
                    ]);
                }

                return $transaction->refresh()->load([
                    'items',
                    'member.homeBranch',
                    'branch',
                    'cashier',
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Layer 2 Idempotency fallback if two concurrent requests hit the unique constraint simultaneously (PRD 6.3)
            $concurrentTransaction = Transaction::with(['items', 'member.homeBranch', 'branch', 'cashier'])
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($concurrentTransaction) {
                return $this->validateExistingIdempotentTransaction($concurrentTransaction, $branch->id, $cashier->id);
            }

            throw $e;
        }
    }

    /**
     * Generate unique invoice number with format INV-{BRANCH_CODE}-{YYYYMMDD}-{SEQ4}.
     */
    public function generateInvoiceNumber(Branch $branch): string
    {
        $datePart = now()->format('Ymd');
        $prefix = "INV-{$branch->code}-{$datePart}-";

        $existingInvoices = Transaction::query()
            ->where('invoice_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('invoice_number');

        $maxSeq = 0;

        foreach ($existingInvoices as $inv) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $inv, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = Transaction::where('invoice_number', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }

    private function validateExistingIdempotentTransaction(
        Transaction $transaction,
        int $branchId,
        int $cashierId
    ): Transaction {
        if ((int) $transaction->branch_id !== $branchId || (int) $transaction->cashier_id !== $cashierId) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key sudah digunakan oleh sesi kasir lain.',
            ]);
        }

        return $transaction;
    }
}
