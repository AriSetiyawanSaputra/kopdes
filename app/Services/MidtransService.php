<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MidtransService
{
    /**
     * Request a Snap Token from Midtrans Snap API with a 15-minute expiry and order_id = invoice_number.
     * Throws an HTTP 502 exception on network/gateway failure so CheckoutService rolls back the transaction.
     */
    public function createSnapToken(Transaction $transaction): string
    {
        $transaction->loadMissing(['items', 'member', 'branch', 'cashier']);

        $itemDetails = $transaction->items->map(fn (TransactionItem $item) => [
            'id' => (string) $item->product_id,
            'price' => (int) round((float) $item->unit_price_snapshot),
            'quantity' => (int) $item->qty,
            'name' => mb_substr((string) $item->product_name_snapshot, 0, 50),
        ])->values()->all();

        $payload = [
            'transaction_details' => [
                'order_id' => $transaction->invoice_number,
                'gross_amount' => (int) round((float) $transaction->total_amount),
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $transaction->member?->name ?? 'Pembeli Umum KOPDES',
                'phone' => $transaction->member?->phone ?? ($transaction->branch?->phone ?? ''),
            ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => 15,
            ],
        ];

        $serverKey = (string) config('services.midtrans.server_key', '');
        $snapUrl = (string) config(
            'services.midtrans.snap_url',
            'https://app.sandbox.midtrans.com/snap/v1/transactions'
        );

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($snapUrl, $payload);
        } catch (ConnectionException $e) {
            throw new HttpException(
                502,
                'Gagal menghubungi layanan pembayaran Midtrans Snap. Silakan coba lagi.',
                $e
            );
        }

        if ($response->failed()) {
            throw new HttpException(
                502,
                'Midtrans Snap menolak permintaan pembuatan token pembayaran.'
            );
        }

        $snapToken = trim((string) $response->json('token', ''));

        if ($snapToken === '') {
            throw new HttpException(
                502,
                'Midtrans Snap tidak mengembalikan token pembayaran yang valid.'
            );
        }

        return $snapToken;
    }

    /**
     * Fetch the latest transaction status from Midtrans Core API (GET /v2/{order_id}/status).
     *
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $orderId): array
    {
        $serverKey = (string) config('services.midtrans.server_key', '');
        $apiUrl = rtrim((string) config('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2'), '/');

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(15)
                ->get("{$apiUrl}/{$orderId}/status");
        } catch (ConnectionException $e) {
            throw new HttpException(
                502,
                'Gagal menghubungi Midtrans Status API.',
                $e
            );
        }

        if ($response->failed()) {
            throw new HttpException(
                502,
                "Gagal memeriksa status transaksi {$orderId} dari Midtrans."
            );
        }

        return (array) $response->json();
    }

    /**
     * Cancel a pending transaction in Midtrans Core API (POST /v2/{order_id}/cancel).
     *
     * @return array<string, mixed>
     */
    public function cancelTransaction(string $orderId): array
    {
        $serverKey = (string) config('services.midtrans.server_key', '');
        $apiUrl = rtrim((string) config('services.midtrans.api_url', 'https://api.sandbox.midtrans.com/v2'), '/');

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post("{$apiUrl}/{$orderId}/cancel");
        } catch (ConnectionException $e) {
            throw new HttpException(
                502,
                'Gagal menghubungi Midtrans Cancel API.',
                $e
            );
        }

        if ($response->failed()) {
            throw new HttpException(
                502,
                "Gagal membatalkan transaksi {$orderId} di Midtrans."
            );
        }

        return (array) $response->json();
    }

    /**
     * Verify Midtrans webhook SHA-512 signature_key using timing-attack-safe hash_equals().
     * Accepts either a full webhook payload array or individual ($orderId, $statusCode, $grossAmount, $signatureKey) strings.
     *
     * @param  array<string, mixed>|string  $payloadOrOrderId
     */
    public function verifySignature(
        array|string $payloadOrOrderId,
        ?string $statusCode = null,
        ?string $grossAmount = null,
        ?string $signatureKey = null,
    ): bool {
        if (is_array($payloadOrOrderId)) {
            $orderId = (string) ($payloadOrOrderId['order_id'] ?? '');
            $statusCode = (string) ($payloadOrOrderId['status_code'] ?? '');
            $grossAmount = (string) ($payloadOrOrderId['gross_amount'] ?? '');
            $signatureKey = (string) ($payloadOrOrderId['signature_key'] ?? '');
        } else {
            $orderId = $payloadOrOrderId;
            $statusCode = (string) $statusCode;
            $grossAmount = (string) $grossAmount;
            $signatureKey = (string) $signatureKey;
        }

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $serverKey = (string) config('services.midtrans.server_key', '');

        $expectedSignature = hash(
            'sha512',
            $orderId.$statusCode.$grossAmount.$serverKey
        );

        return hash_equals($expectedSignature, $signatureKey);
    }
}
