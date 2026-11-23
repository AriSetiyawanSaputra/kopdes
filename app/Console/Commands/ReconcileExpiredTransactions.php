<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MidtransReconciliationService;
use App\Services\MidtransService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileExpiredTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kopdes:reconcile-expired-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rekonsiliasi transaksi Midtrans pending yang telah melewati expires_at dan lepaskan reserved_qty';

    /**
     * Execute the console command.
     */
    public function handle(
        MidtransService $midtransService,
        MidtransReconciliationService $reconciliationService
    ): int {
        $expiredTransactions = Transaction::query()
            ->where('payment_method', 'midtrans')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        if ($expiredTransactions->isEmpty()) {
            $this->info('Tidak ada transaksi Midtrans pending yang kedaluwarsa.');

            return self::SUCCESS;
        }

        $reconciledCount = 0;

        foreach ($expiredTransactions as $transaction) {
            $payload = [
                'order_id' => $transaction->invoice_number,
                'transaction_status' => 'expire',
                'gross_amount' => (string) $transaction->total_amount,
            ];

            try {
                $statusResponse = $midtransService->getTransactionStatus($transaction->invoice_number);

                if (! empty($statusResponse)) {
                    $remoteStatus = strtolower(trim((string) ($statusResponse['transaction_status'] ?? '')));
                    $fraudStatus = strtolower(trim((string) ($statusResponse['fraud_status'] ?? 'accept')));

                    $isPaidAtLastSecond = $remoteStatus === 'settlement'
                        || ($remoteStatus === 'capture' && ($fraudStatus === 'accept' || $fraudStatus === ''));

                    $payload = array_merge($statusResponse, [
                        'order_id' => $transaction->invoice_number,
                        'transaction_status' => $isPaidAtLastSecond ? $remoteStatus : 'expire',
                        'gross_amount' => $statusResponse['gross_amount'] ?? (string) $transaction->total_amount,
                    ]);
                }
            } catch (Throwable) {
                // If Midtrans Status API is unreachable or returns 404 for an uninitiated Snap session,
                // expire the transaction and release reserved_qty.
            }

            $reconciliationService->applyStatus(
                $payload,
                'scheduler_check',
                true
            );

            $reconciledCount++;
        }

        $this->info("Berhasil merekonsiliasi {$reconciledCount} transaksi Midtrans yang kedaluwarsa.");

        return self::SUCCESS;
    }
}
