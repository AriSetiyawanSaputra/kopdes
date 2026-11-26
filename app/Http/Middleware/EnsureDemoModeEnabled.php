<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoModeEnabled
{
    /**
     * Block demo-only endpoints with HTTP 404 Not Found when APP_DEMO_MODE is false (PRD_PHASE3 §6.5).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('kopdes.demo_mode', false)) {
            abort(404);
        }

        return $next($request);
    }
}
