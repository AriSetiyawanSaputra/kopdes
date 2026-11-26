<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberAuthenticated
{
    /**
     * Ensure the incoming request is authenticated on the isolated 'member' guard
     * and the member account remains active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('member');

        if (! $guard->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Silakan login terlebih dahulu ke Portal Member.',
                ], 401);
            }

            return redirect()->guest(route('member.login'));
        }

        /** @var Member|null $member */
        $member = $guard->user();

        if (! $member || ! $member->isActive() || empty($member->pin_hash)) {
            $guard->logout();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun portal member tidak aktif. Hubungi Admin KOPDES asal Anda.',
                ], 403);
            }

            return redirect()
                ->route('member.login')
                ->with('error', 'Akun portal belum aktif. Hubungi Admin KOPDES asal Anda.');
        }

        return $next($request);
    }
}
