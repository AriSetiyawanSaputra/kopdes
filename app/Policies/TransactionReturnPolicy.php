<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;

class TransactionReturnPolicy
{
    /**
     * Seluruh role admin dapat melihat riwayat retur dalam scope wilayahnya. Kasir ditolak HTTP 403.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Otorisasi pembacaan 1 dokumen retur sesuai hierarki wilayah (anti-IDOR).
     */
    public function view(User $actor, TransactionReturn $return): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $return->branch_id === (int) $actor->branch_id;
        }

        $branch = $return->branch;
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
     * Hanya admin_nasional atau admin_cabang pemilik cabang transaksi yang dapat mengeksekusi retur/void.
     * admin_provinsi, admin_kabupaten, kasir, dan admin_cabang cabang lain ditolak HTTP 403.
     */
    public function create(User $actor, ?Transaction $transaction = null): bool
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

            if ($transaction !== null && (int) $transaction->branch_id !== (int) $actor->branch_id) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Data retur bersifat immutable (FR-P2-08).
     */
    public function update(User $actor, TransactionReturn $return): bool
    {
        return false;
    }

    /**
     * Data retur bersifat immutable (FR-P2-08).
     */
    public function delete(User $actor, TransactionReturn $return): bool
    {
        return false;
    }
}
