<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockMovement;
use App\Models\User;

class StockPolicy
{
    /**
     * Determine whether the actor can view branch stock and stock movement logs.
     */
    public function viewAny(User $actor, ?Branch $branch = null): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($branch === null) {
            return true;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => true,
            User::ROLE_ADMIN_PROVINSI => $branch->province_id === $actor->province_id,
            User::ROLE_ADMIN_KABUPATEN => $branch->regency_id === $actor->regency_id,
            User::ROLE_ADMIN_CABANG => (int) $branch->id === (int) $actor->branch_id,
            default => false,
        };
    }

    /**
     * Determine whether the actor can view a specific branch stock or stock movement record.
     */
    public function view(User $actor, BranchStock|StockMovement $record): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $record->branch_id === (int) $actor->branch_id;
        }

        $branch = $record->branch;

        if (! $branch) {
            return false;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_PROVINSI => $branch->province_id === $actor->province_id,
            User::ROLE_ADMIN_KABUPATEN => $branch->regency_id === $actor->regency_id,
            default => false,
        };
    }

    /**
     * Determine whether the actor can restock or adjust inventory on a branch.
     * Per RBAC Matrix (PRD 2.2): Only admin_cabang can perform CRU (restock & adjustment) on their own branch.
     */
    public function mutate(User $actor, ?Branch $branch = null): bool
    {
        if (! $actor->is_active || ! $actor->isAdminCabang()) {
            return false;
        }

        if ($branch === null) {
            return $actor->branch_id !== null;
        }

        return (int) $branch->id === (int) $actor->branch_id;
    }

    public function restock(User $actor, ?Branch $branch = null): bool
    {
        return $this->mutate($actor, $branch);
    }

    public function adjust(User $actor, ?Branch $branch = null): bool
    {
        return $this->mutate($actor, $branch);
    }
}
