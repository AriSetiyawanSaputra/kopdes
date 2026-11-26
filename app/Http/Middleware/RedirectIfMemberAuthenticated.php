<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfMemberAuthenticated
{
    /**
     * Redirect already-authenticated members away from /member/login to /member.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('member')->check()) {
            return redirect()->route('member.dashboard');
        }

        return $next($request);
    }
}
