<?php

namespace App\Http\Middleware;

use App\Models\CashierShift;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveShift
{
    /**
     * Tolak request checkout POS dengan HTTP 422 jika kasir belum membuka shift berstatus 'open' (FR-P2-01).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cashier = $request->user();

        if (! $cashier || ! $cashier->isCashier() || ! $cashier->branch_id) {
            abort(403, 'Hanya kasir aktif yang dapat memproses transaksi POS.');
        }

        $hasOpenShift = CashierShift::query()
            ->where('cashier_id', $cashier->id)
            ->where('branch_id', $cashier->branch_id)
            ->where('status', CashierShift::STATUS_OPEN)
            ->exists();

        if (! $hasOpenShift) {
            throw ValidationException::withMessages([
                'shift' => 'Anda belum membuka shift kasir. Silakan buka shift dengan mengisi modal awal laci terlebih dahulu.',
            ]);
        }

        return $next($request);
    }
}
