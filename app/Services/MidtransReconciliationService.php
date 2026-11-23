<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\PaymentLog;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class MidtransReconciliationService
{
    /**
     * Single source of truth for reconciling Midtrans transaction statuses across:
     * 1. Webhook notifications ('webhook')
     * 2. Cashier manual status check / cancel ('manual_check')
     * 3. Scheduled expired transaction cleanup ('scheduler_check')
     *
     * Supports both:
     * - applyStatus(array $payload, string $source = 'webhook', bool $signatureVerified = true)
     * - applyStatus(Transaction|string $transactionOrOrderId, array $payload, string $source = 'manual_check', bool $signatureVerified = true)
     *
     * @param  array<string, mixed>|Transaction|string  $payloadOrTransaction
     * @param  array<string, mixed>|string  $sourceOrPayload
     */
    public function applyStatus(
        array|Transaction|string $payloadOrTransaction,
        array|string $sourceOrPayload = 'webhook',
        string|bool $signatureVerifiedOrSource = true,
        bool $signatureVerified = true,
    ): ?Transaction {
        if (is_array($payloadOrTransaction)) {
            $payload = $payloadOrTransaction;
            $source = is_string($sourceOrPayload) ? $sourceOrPayload : 'webhook';
            $verified = is_bool($signatureVerifiedOrSource) ? $signatureVerifiedOrSource : $signatureVerified;
            $orderId = trim((string) ($payload['order_id'] ?? ''));
        } else {
            $payload = is_array($sourceOrPayload) ? $sourceOrPayload : [];
            $source = is_string($signatureVerifiedOrSource) ? $signatureVerifiedOrSource : 'manual_check';
            $verified = $signatureVerified;
            $orderId = $payloadOrTransaction instanceof Transaction
                ? $payloadOrTransaction->invoice_number
                : trim((string) ($payload['order_id'] ?? $payloadOrTransaction));
        }

        $normalizedSource = in_array($source, ['webhook', 'manual_check', 'scheduler_check'], true)
            ? $source
            : 'webhook';

        return DB::transaction(function () use ($orderId, $payload, $normalizedSource, $verified): ?Transaction {
            $transaction = $orderId !== ''
                ? Transaction::query()
                    ->with('items')
                    ->where('invoice_number', $orderId)
                    ->lockForUpdate()
                    ->first()
                : null;

            $payloadGrossAmount = isset($payload['gross_amount']) && $payload['gross_amount'] !== ''
                ? round((float) $payload['gross_amount'], 2)
                : ($transaction ? round((float) $transaction->total_amount, 2) : 0.0);

            $expectedGrossAmount = $transaction ? round((float) $transaction->total_amount, 2) : null;
            $isGrossAmountValid = $expectedGrossAmount === null
                || ! isset($payload['gross_amount'])
                || abs($payloadGrossAmount - $expectedGrossAmount) < 0.01;

            $loggedPayload = $payload;
            if (! $isGrossAmountValid) {
                $loggedPayload['_reconciliation_error'] = 'gross_amount_mismatch';
            }

            PaymentLog::create([
                'transaction_id' => $transaction?->id,
                'gateway' => 'midtrans',
                'source' => $normalizedSource,
                'external_order_id' => $orderId !== '' ? $orderId : 'UNKNOWN',
                'midtrans_transaction_id' => isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
                'payment_type' => isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
                'transaction_status' => (string) ($payload['transaction_status'] ?? 'unknown'),
                'fraud_status' => isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
                'gross_amount' => $payloadGrossAmount,
                'signature_verified' => $verified,
                'raw_payload' => $loggedPayload,
                'created_at' => now(),
            ]);

            if (! $transaction || ! $verified || ! $isGrossAmountValid) {
                return $transaction;
            }

            // Idempotency Guard: if status is already final (paid, expired, cancelled, failed), stop immediately (PRD 6.2)
            if ($transaction->payment_status !== 'pending') {
                return $transaction->load(['items', 'member.homeBranch', 'branch', 'cashier']);
            }

            $midtransStatus = strtolower(trim((string) ($payload['transaction_status'] ?? '')));
            $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? 'accept')));
            $paymentType = isset($payload['payment_type']) && trim((string) $payload['payment_type']) !== ''
                ? trim((string) $payload['payment_type'])
                : $transaction->midtrans_payment_type;

            $mappedStatus = $this->mapMidtransStatus($midtransStatus, $fraudStatus);

            if ($mappedStatus === 'pending') {
                if ($paymentType !== $transaction->midtrans_payment_type) {
                    $transaction->update([
                        'midtrans_payment_type' => $paymentType,
                    ]);
                }

                return $transaction->refresh()->load(['items', 'member.homeBranch', 'branch', 'cashier']);
            }

            // Lock branch_stocks ordered by product_id ASC to prevent deadlocks (PRD 6.1 & AGENTS 3.2)
            $itemsByProduct = $transaction->items
                ->groupBy('product_id')
                ->map(fn ($group) => (int) $group->sum('qty'))
                ->sortKeys();

            $sortedProductIds = $itemsByProduct->keys()->map(fn ($id) => (int) $id)->all();

            $stocks = BranchStock::where('branch_id', $transaction->branch_id)
                ->whereIn('product_id', $sortedProductIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            if ($mappedStatus === 'paid') {
                foreach ($itemsByProduct as $productId => $qty) {
                    /** @var BranchStock|null $stock */
                    $stock = $stocks->get((int) $productId);

                    if (! $stock) {
                        continue;
                    }

                    $qtyBefore = (int) $stock->quantity;
                    $qtyAfter = $qtyBefore - $qty;
                    $newReserved = max(0, (int) $stock->reserved_qty - $qty);

                    $stock->update([
                        'quantity' => $qtyAfter,
                        'reserved_qty' => $newReserved,
                    ]);

                    StockMovement::create([
                        'branch_id' => $transaction->branch_id,
                        'product_id' => (int) $productId,
                        'user_id' => $transaction->cashier_id,
                        'type' => 'sale',
                        'qty_change' => -$qty,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $qtyAfter,
                        'reference_type' => Transaction::class,
                        'reference_id' => $transaction->id,
                        'notes' => "Penjualan Midtrans POS ({$transaction->invoice_number})",
                        'created_at' => now(),
                    ]);
                }

                $transaction->update([
                    'payment_status' => 'paid',
                    'midtrans_payment_type' => $paymentType,
                    'paid_at' => now(),
                ]);
            } elseif (in_array($mappedStatus, ['expired', 'cancelled', 'failed'], true)) {
                foreach ($itemsByProduct as $productId => $qty) {
                    /** @var BranchStock|null $stock */
                    $stock = $stocks->get((int) $productId);

                    if (! $stock) {
                        continue;
                    }

                    $newReserved = max(0, (int) $stock->reserved_qty - $qty);

                    $stock->update([
                        'reserved_qty' => $newReserved,
                    ]);
                }

                $transaction->update([
                    'payment_status' => $mappedStatus,
                    'midtrans_payment_type' => $paymentType,
                ]);
            }

            return $transaction->refresh()->load([
                'items',
                'member.homeBranch',
                'branch',
                'cashier',
            ]);
        });
    }

    /**
     * Record a security audit entry in payment_logs when a webhook arrives with an invalid SHA-512 signature.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordInvalidSignatureLog(array $payload, string $source = 'webhook'): PaymentLog
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        $transaction = $orderId !== ''
            ? Transaction::where('invoice_number', $orderId)->first()
            : null;

        return PaymentLog::create([
            'transaction_id' => $transaction?->id,
            'gateway' => 'midtrans',
            'source' => in_array($source, ['webhook', 'manual_check', 'scheduler_check'], true) ? $source : 'webhook',
            'external_order_id' => $orderId !== '' ? $orderId : 'UNKNOWN',
            'midtrans_transaction_id' => isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            'payment_type' => isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            'transaction_status' => (string) ($payload['transaction_status'] ?? 'unknown'),
            'fraud_status' => isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
            'gross_amount' => isset($payload['gross_amount']) && $payload['gross_amount'] !== ''
                ? round((float) $payload['gross_amount'], 2)
                : 0.0,
            'signature_verified' => false,
            'raw_payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Map Midtrans transaction_status & fraud_status to KOPDES payment_status enum (PRD 6.2 item 5).
     */
    public function mapMidtransStatus(string $transactionStatus, string $fraudStatus = 'accept'): string
    {
        if ($transactionStatus === 'settlement') {
            return 'paid';
        }

        if ($transactionStatus === 'capture') {
            if ($fraudStatus === 'accept' || $fraudStatus === '') {
                return 'paid';
            }

            if ($fraudStatus === 'deny') {
                return 'failed';
            }

            return 'pending';
        }

        return match ($transactionStatus) {
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'deny' => 'failed',
            default => 'pending',
        };
    }
}
