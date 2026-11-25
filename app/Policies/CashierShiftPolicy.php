<?php

namespace App\Policies;

use App\Models\CashierShift;
use App\Models\User;

class CashierShiftPolicy
{
    /**
     * Seluruh role admin dapat melihat daftar riwayat shift dalam scope wilayahnya.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Admin dalam scope wilayah atau kasir pemilik shift dapat melihat detail/ringkasan shift.
     */
    public function view(User $actor, CashierShift $shift): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isCashier()) {
            return (int) $shift->cashier_id === (int) $actor->id
                && (int) $shift->branch_id === (int) $actor->branch_id;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $shift->branch_id === (int) $actor->branch_id;
        }

        $branch = $shift->branch;
        if (! $branch) {
            return false;
        }

        if ($actor->isAdminProvinsi()) {
            return $branch->province_id === $actor->province_id;
        }

        if ($actor->isAdminKabupaten()) {
            return $branch->regency_id === $actor->regency_id;
        }

        return false;
    }

    /**
     * Hanya kasir aktif di cabang aktif yang dapat membuka shift di POS.
     */
    public function open(User $actor): bool
    {
        return $actor->is_active
            && $actor->isCashier()
            && $actor->branch_id !== null
            && (bool) $actor->branch?->is_active;
    }

    /**
     * Hanya kasir pemilik shift yang dapat mencatat mutasi kas laci (cash_in/cash_out).
     */
    public function recordMovement(User $actor, CashierShift $shift): bool
    {
        return $actor->is_active
            && $actor->isCashier()
            && (int) $shift->cashier_id === (int) $actor->id
            && (int) $shift->branch_id === (int) $actor->branch_id;
    }

    /**
     * Hanya kasir pemilik shift yang dapat menutup shift miliknya sendiri di layar POS.
     */
    public function close(User $actor, CashierShift $shift): bool
    {
        return $actor->is_active
            && $actor->isCashier()
            && (int) $shift->cashier_id === (int) $actor->id
            && (int) $shift->branch_id === (int) $actor->branch_id;
    }

    /**
     * Hanya admin_nasional atau admin_cabang pemilik cabang yang berhak melakukan force-close shift.
     * admin_provinsi, admin_kabupaten, kasir, dan admin_cabang cabang lain ditolak HTTP 403.
     */
    public function forceClose(User $actor, CashierShift $shift): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $shift->branch_id === (int) $actor->branch_id;
        }

        return false;
    }
}
