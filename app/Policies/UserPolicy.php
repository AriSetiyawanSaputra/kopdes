<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor can view the user list (within their region scope).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Determine whether the actor can view a target user (anti-IDOR).
     */
    public function view(User $actor, User $target): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => true,
            User::ROLE_ADMIN_PROVINSI => $target->province_id === $actor->province_id
                && in_array($target->role, [
                    User::ROLE_ADMIN_KABUPATEN,
                    User::ROLE_ADMIN_CABANG,
                    User::ROLE_KASIR,
                ], true),
            User::ROLE_ADMIN_KABUPATEN => $target->regency_id === $actor->regency_id
                && in_array($target->role, [
                    User::ROLE_ADMIN_CABANG,
                    User::ROLE_KASIR,
                ], true),
            User::ROLE_ADMIN_CABANG => (int) $target->branch_id === (int) $actor->branch_id
                && $target->role === User::ROLE_KASIR,
            default => false,
        };
    }

    /**
     * Determine whether the actor can create a user with the given target role and region/branch scope.
     */
    public function create(
        User $actor,
        ?string $targetRole = null,
        ?string $provinceId = null,
        ?string $regencyId = null,
        ?int $branchId = null
    ): bool {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($targetRole === null) {
            return true;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => in_array($targetRole, [
                User::ROLE_ADMIN_NASIONAL,
                User::ROLE_ADMIN_PROVINSI,
                User::ROLE_ADMIN_KABUPATEN,
                User::ROLE_ADMIN_CABANG,
                User::ROLE_KASIR,
            ], true),
            User::ROLE_ADMIN_PROVINSI => $targetRole === User::ROLE_ADMIN_KABUPATEN
                && ($provinceId === null || $provinceId === $actor->province_id),
            User::ROLE_ADMIN_KABUPATEN => $targetRole === User::ROLE_ADMIN_CABANG
                && ($provinceId === null || $provinceId === $actor->province_id)
                && ($regencyId === null || $regencyId === $actor->regency_id),
            User::ROLE_ADMIN_CABANG => $targetRole === User::ROLE_KASIR
                && ($branchId === null || (int) $branchId === (int) $actor->branch_id),
            default => false,
        };
    }

    /**
     * Determine whether the actor can update a target user.
     */
    public function update(User $actor, User $target): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => true,
            User::ROLE_ADMIN_PROVINSI => $target->role === User::ROLE_ADMIN_KABUPATEN
                && $target->province_id === $actor->province_id,
            User::ROLE_ADMIN_KABUPATEN => $target->role === User::ROLE_ADMIN_CABANG
                && $target->regency_id === $actor->regency_id,
            User::ROLE_ADMIN_CABANG => $target->role === User::ROLE_KASIR
                && (int) $target->branch_id === (int) $actor->branch_id,
            default => false,
        };
    }

    /**
     * Determine whether the actor can activate/deactivate a target user.
     */
    public function toggleActive(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id && $this->update($actor, $target);
    }

    /**
     * Determine whether the actor can delete a target user.
     */
    public function delete(User $actor, User $target): bool
    {
        return (int) $actor->id !== (int) $target->id && $this->update($actor, $target);
    }
}
