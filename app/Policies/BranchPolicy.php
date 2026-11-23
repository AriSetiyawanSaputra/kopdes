<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any branches (within their scope).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Determine whether the user can view a specific branch (anti-IDOR).
     */
    public function view(User $actor, Branch $branch): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
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
     * Determine whether the user can create a branch (optionally within a target regency/province).
     */
    public function create(User $actor, ?string $regencyId = null, ?string $provinceId = null): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminKabupaten()) {
            if ($provinceId !== null && $provinceId !== $actor->province_id) {
                return false;
            }

            if ($regencyId !== null && $regencyId !== $actor->regency_id) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the branch.
     */
    public function update(User $actor, Branch $branch): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => true,
            User::ROLE_ADMIN_KABUPATEN => $branch->regency_id === $actor->regency_id,
            default => false,
        };
    }

    /**
     * Determine whether the user can activate/deactivate the branch.
     */
    public function toggleActive(User $actor, Branch $branch): bool
    {
        return $this->update($actor, $branch);
    }

    /**
     * Determine whether the user can delete the branch.
     */
    public function delete(User $actor, Branch $branch): bool
    {
        return $actor->is_active && $actor->isAdminNasional();
    }
}
