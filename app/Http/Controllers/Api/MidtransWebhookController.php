<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransReconciliationService;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtransService,
        private readonly MidtransReconciliationService $reconciliationService,
    ) {}

    /**
     * Handle incoming Midtrans HTTP webhook notifications (POST /api/webhooks/midtrans).
     * Verifies SHA-512 signature_key (logs & returns 403 on failure) and delegates to MidtransReconciliationService.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->midtransService->verifySignature($payload)) {
            $this->reconciliationService->recordInvalidSignatureLog($payload, 'webhook');

            return response()->json([
                'message' => 'Invalid signature',
            ], 403);
        }

        $transaction = $this->reconciliationService->applyStatus(
            $payload,
            'webhook',
            true
        );

        return response()->json([
            'status' => 'ok',
            'order_id' => $payload['order_id'] ?? null,
            'payment_status' => $transaction?->payment_status,
        ], 200);
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->handle($request);
    }
}
