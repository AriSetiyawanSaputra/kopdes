<?php

namespace App\Policies;

use App\Models\OperationalExpense;
use App\Models\User;

class OperationalExpensePolicy
{
    /**
     * Seluruh role admin (nasional, provinsi, kabupaten, cabang) dapat melihat biaya operasional
     * dalam scope wilayahnya. Kasir dilarang total (HTTP 403).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Otorisasi pembacaan 1 record biaya operasional berdasarkan hierarki wilayah (anti-IDOR).
     */
    public function view(User $actor, OperationalExpense $expense): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $expense->branch_id === (int) $actor->branch_id;
        }

        $branch = $expense->branch;
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
     * Hanya admin_cabang (khusus cabang miliknya) dan admin_nasional (dengan memilih cabang eksplisit)
     * yang berhak mencatat biaya operasional baru. admin_provinsi, admin_kabupaten, dan kasir ditolak HTTP 403.
     */
    public function create(User $actor, ?int $targetBranchId = null): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            if (! $actor->branch_id) {
                return false;
            }

            if ($targetBranchId !== null && (int) $targetBranchId !== (int) $actor->branch_id) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Hanya admin_nasional atau admin_cabang pemilik cabang yang berhak mengubah biaya operasional.
     */
    public function update(User $actor, OperationalExpense $expense): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $expense->branch_id === (int) $actor->branch_id;
        }

        return false;
    }

    /**
     * Hanya admin_nasional atau admin_cabang pemilik cabang yang berhak menghapus (soft-delete) biaya operasional.
     */
    public function delete(User $actor, OperationalExpense $expense): bool
    {
        return $this->update($actor, $expense);
    }
}
