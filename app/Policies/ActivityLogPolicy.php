<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    /**
     * Seluruh role Admin (Nasional, Provinsi, Kabupaten, Cabang) dapat melihat Audit Trail sesuai scope wilayah.
     * Kasir ditolak HTTP 403.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Detail log audit dibatasi ketat oleh scope wilayah (log global province_id = NULL hanya untuk Admin Nasional).
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        if ($user->isAdminNasional()) {
            return true;
        }

        if ($user->isAdminProvinsi()) {
            return $activityLog->province_id !== null
                && (string) $activityLog->province_id === (string) $user->province_id;
        }

        if ($user->isAdminKabupaten()) {
            return $activityLog->regency_id !== null
                && (string) $activityLog->regency_id === (string) $user->regency_id;
        }

        if ($user->isAdminCabang()) {
            return $activityLog->branch_id !== null
                && (int) $activityLog->branch_id === (int) $user->branch_id;
        }

        return false;
    }

    /**
     * Audit Trail bersifat immutable (append-only).
     */
    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    /**
     * Audit Trail bersifat immutable (append-only).
     */
    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }
}
