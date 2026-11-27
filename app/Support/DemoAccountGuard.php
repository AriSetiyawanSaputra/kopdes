<?php

namespace App\Support;

use App\Models\Member;
use App\Models\User;

class DemoAccountGuard
{
    /**
     * Seeded portfolio demo admin accounts protected from credential/status tampering when APP_DEMO_MODE=true.
     *
     * @var list<string>
     */
    public const PROTECTED_DEMO_EMAILS = [
        'admin.nasional@kopdes.id',
        'admin.jatim@kopdes.id',
        'admin.ponorogo@kopdes.id',
        'admin.ngunut@kopdes.id',
        'admin.cekok@kopdes.id',
        'admin.balerejo@kopdes.id',
        'admin.biting@kopdes.id',
    ];

    /**
     * Seeded portfolio demo cashier RFID UIDs protected when APP_DEMO_MODE=true.
     *
     * @var list<string>
     */
    public const PROTECTED_DEMO_CASHIER_UIDS = [
        '0008493873',
        '0008305215',
        '0008510003',
        '0008510004',
    ];

    /**
     * Seeded portfolio demo member codes protected when APP_DEMO_MODE=true.
     *
     * @var list<string>
     */
    public const PROTECTED_DEMO_MEMBER_CODES = [
        'MBR-KOP-3502-001-00001',
        'MBR-KOP-3502-002-00001',
    ];

    public static function isDemoMode(): bool
    {
        return (bool) config('kopdes.demo_mode', false);
    }

    public static function isProtectedDemoUser(?User $user): bool
    {
        if (! $user || ! self::isDemoMode()) {
            return false;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if ($email !== '' && in_array($email, self::PROTECTED_DEMO_EMAILS, true)) {
            return true;
        }

        $cardUid = strtoupper(trim((string) ($user->card_uid ?? '')));
        if ($cardUid !== '' && in_array($cardUid, self::PROTECTED_DEMO_CASHIER_UIDS, true)) {
            return true;
        }

        return false;
    }

    public static function isProtectedDemoMember(?Member $member): bool
    {
        if (! $member || ! self::isDemoMode()) {
            return false;
        }

        $code = strtoupper(trim((string) ($member->member_code ?? '')));

        return $code !== '' && in_array($code, self::PROTECTED_DEMO_MEMBER_CODES, true);
    }
}
