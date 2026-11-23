<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    /**
     * Determine whether the actor can view member lists (within their region scope).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Determine whether the actor can view a specific member record in admin backoffice (anti-IDOR).
     */
    public function view(User $actor, Member $member): bool
    {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return (int) $member->home_branch_id === (int) $actor->branch_id;
        }

        $branch = $member->homeBranch;

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
     * Determine whether the actor can register a new member (optionally for a given home_branch_id).
     */
    public function create(User $actor, ?int $homeBranchId = null): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($actor->isAdminCabang()) {
            return $homeBranchId === null || (int) $homeBranchId === (int) $actor->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the actor can update or replace RFID card for a member.
     */
    public function update(User $actor, Member $member): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        return match ($actor->role) {
            User::ROLE_ADMIN_NASIONAL => true,
            User::ROLE_ADMIN_CABANG => (int) $member->home_branch_id === (int) $actor->branch_id,
            default => false,
        };
    }

    /**
     * Controlled portability exception: active cashier can lookup active members across branches at POS.
     */
    public function lookupAtPos(User $actor): bool
    {
        return $actor->is_active && $actor->isCashier();
    }
}
