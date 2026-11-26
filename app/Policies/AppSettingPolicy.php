<?php

namespace App\Policies;

use App\Models\User;

class AppSettingPolicy
{
    /**
     * Hanya Admin Nasional yang diizinkan melihat halaman Pengaturan Sistem.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdminNasional();
    }

    /**
     * Hanya Admin Nasional yang diizinkan memperbarui Pengaturan Sistem.
     */
    public function update(User $user): bool
    {
        return $user->isAdminNasional();
    }
}
