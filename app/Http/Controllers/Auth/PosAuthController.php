<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashierLoginRequest;
use App\Http\Requests\PosUnlockRequest;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosAuthController extends Controller
{
    private const MAX_PIN_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    /**
     * Render the Cashier RFID + 6-Digit PIN Login Screen (/pos/login).
     */
    public function showLogin(): Response
    {
        return Inertia::render('Pos/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Authenticate a cashier via normalized RFID card_uid + 6-digit PIN.
     * Enforces 5 failed attempts rate limit with 900s (15m) decay and returns HTTP 429 when locked out.
     */
    public function login(CashierLoginRequest $request): RedirectResponse|JsonResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $normalizedUid = CardUidNormalizer::normalize((string) $request->validated('card_uid'));
        $pin = (string) $request->validated('pin');
        $throttleKey = $this->throttleKey($normalizedUid, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

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

        $cashier = User::with('branch')
            ->where('card_uid', $normalizedUid)
            ->where('role', User::ROLE_KASIR)
            ->first();

        if (! $cashier || ! $cashier->pin_hash || ! Hash::check($pin, $cashier->pin_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'Kartu RFID kasir atau PIN 6 digit tidak valid.',
            ]);
        }

        if (! $cashier->is_active) {
            throw ValidationException::withMessages([
                'card_uid' => 'Akun kasir Anda sedang dinonaktifkan. Hubungi Admin Cabang.',
            ]);
        }

        if (! $cashier->branch || ! $cashier->branch->is_active) {
            throw ValidationException::withMessages([
                'card_uid' => 'Cabang KOPDES Anda sedang dinonaktifkan.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('web')->login($cashier);
        $request->session()->regenerate();
        $request->session()->put('pos_locked', false);

        return redirect()->intended(route('pos.index', absolute: false));
    }

    /**
     * Lock the active POS session screen (triggered by F12 Quick Lock or 15-minute idle timer).
     */
    public function lock(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->put('pos_locked', true);

        if ($request->expectsJson()) {
            return response()->json([
                'pos_locked' => true,
                'message' => 'Layar POS berhasil dikunci.',
            ]);
        }

        return redirect()->back()->with('info', 'Layar POS terkunci.');
    }

    /**
     * Unlock the POS session screen using RFID card_uid + 6-digit PIN.
     * Preserves cart if unlocked by the same cashier; signals cart reset if unlocked by a different cashier in the same branch.
     */
    public function unlock(PosUnlockRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $currentCashier */
        $currentCashier = $request->user();
        $normalizedUid = CardUidNormalizer::normalize((string) $request->validated('card_uid'));
        $pin = (string) $request->validated('pin');
        $throttleKey = $this->throttleKey($normalizedUid, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak percobaan PIN salah. Silakan tunggu {$retryAfter} detik.",
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

        $targetCashier = User::with('branch')
            ->where('card_uid', $normalizedUid)
            ->where('role', User::ROLE_KASIR)
            ->first();

        if (! $targetCashier || ! $targetCashier->pin_hash || ! Hash::check($pin, $targetCashier->pin_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'Kartu RFID kasir atau PIN 6 digit tidak valid.',
            ]);
        }

        if (! $targetCashier->is_active || ! $targetCashier->branch || ! $targetCashier->branch->is_active) {
            throw ValidationException::withMessages([
                'card_uid' => 'Akun kasir atau cabang KOPDES sedang tidak aktif.',
            ]);
        }

        if ((int) $targetCashier->branch_id !== (int) $currentCashier->branch_id) {
            throw ValidationException::withMessages([
                'card_uid' => 'Kartu kasir berasal dari cabang KOPDES yang berbeda.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $switchedCashier = (int) $targetCashier->id !== (int) $currentCashier->id;

        if ($switchedCashier) {
            Auth::guard('web')->login($targetCashier);
            $request->session()->regenerate();
        }

        $request->session()->put('pos_locked', false);

        if ($request->expectsJson()) {
            return response()->json([
                'pos_locked' => false,
                'switched_cashier' => $switchedCashier,
                'cashier' => [
                    'id' => $targetCashier->id,
                    'name' => $targetCashier->name,
                    'branch_id' => $targetCashier->branch_id,
                ],
                'message' => $switchedCashier
                    ? "Layar POS dibuka oleh {$targetCashier->name}. Keranjang direset untuk kasir baru."
                    : 'Layar POS berhasil dibuka kembali.',
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Layar POS berhasil dibuka kembali.');
    }

    /**
     * Log the cashier out of the POS session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pos.login');
    }

    /**
     * Build the RateLimiter key per PRD Section 6.5: 'kasir-pin:' . $normalizedUid . '|' . $ip
     */
    private function throttleKey(string $normalizedUid, string $ip): string
    {
        return 'kasir-pin:'.$normalizedUid.'|'.$ip;
    }
}
