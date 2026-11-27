<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberLoginRequest;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MemberAuthController extends Controller
{
    private const MAX_PIN_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    /**
     * Render the Portal Member login page (/member/login).
     */
    public function showLogin(): Response
    {
        return Inertia::render('Member/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Authenticate a cooperative member using member_code OR phone + 6-digit PIN on the isolated 'member' guard.
     * Protected by RateLimiter: max 5 failed attempts, 15 minutes (900s) lockout returning HTTP 429 (FR-P3-09).
     */
    public function login(MemberLoginRequest $request): RedirectResponse|JsonResponse
    {
        $identifier = trim((string) $request->validated('identifier'));
        $pin = (string) $request->validated('pin');
        $ip = (string) $request->ip();
        $throttleKey = $this->throttleKey($identifier, $ip);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS)) {
            return $this->tooManyAttemptsResponse($throttleKey);
        }

        // First match by member_code (case-insensitive), fallback to exact phone number
        $matches = Member::query()
            ->whereRaw('UPPER(member_code) = ?', [strtoupper($identifier)])
            ->get();

        if ($matches->isEmpty()) {
            $matches = Member::query()
                ->where('phone', $identifier)
                ->get();
        }

        if ($matches->count() > 1) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identifier' => 'Nomor HP terdaftar pada lebih dari satu anggota. Silakan login menggunakan Kode Member Anda.',
            ]);
        }

        /** @var Member|null $member */
        $member = $matches->first();

        if (! $member) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identifier' => 'Kode Member / Nomor HP atau PIN 6 digit tidak valid.',
            ]);
        }

        // SEC-08: Kunci throttle sekunder berbasis member_id agar pergantian member_code <-> phone tetap terkena limit 5x
        $memberThrottleKey = $this->memberThrottleKey($member->id, $ip);
        if (RateLimiter::tooManyAttempts($memberThrottleKey, self::MAX_PIN_ATTEMPTS)) {
            return $this->tooManyAttemptsResponse($memberThrottleKey);
        }

        if (! $member->isActive() || empty($member->pin_hash) || ! Hash::check($pin, $member->pin_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            RateLimiter::hit($memberThrottleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identifier' => 'Kode Member / Nomor HP atau PIN 6 digit tidak valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($memberThrottleKey);

        Auth::guard('member')->login($member, (bool) $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('member.dashboard', absolute: false));
    }

    /**
     * Log the member out of the isolated 'member' guard without disturbing any active 'web' guard session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('member.login');
    }

    private function tooManyAttemptsResponse(string $key): JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'message' => "Terlalu banyak percobaan PIN salah. Akun diblokir sementara, silakan tunggu {$retryAfter} detik.",
            'retry_after' => $retryAfter,
            'errors' => [
                'pin' => [
                    "Terlalu banyak percobaan PIN salah. Silakan coba lagi dalam {$retryAfter} detik.",
                ],
            ],
        ], 429, [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    /**
     * Build the RateLimiter key per PRD_PHASE3.md FR-P3-09: member-pin:{identifier}|{ip}
     */
    private function throttleKey(string $identifier, string $ip): string
    {
        return 'member-pin:'.strtolower(trim($identifier)).'|'.$ip;
    }

    private function memberThrottleKey(int $memberId, string $ip): string
    {
        return 'member-pin-id:'.$memberId.'|'.$ip;
    }
}
