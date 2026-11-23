<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosUnlocked
{
    /**
     * Reject POS operational requests when the cashier screen is locked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) $request->session()->get('pos_locked', false) === true) {
            abort(403, 'Layar POS sedang terkunci. Silakan buka kunci layar dengan Tap Kartu & PIN Kasir.');
        }

        return $next($request);
    }
}
