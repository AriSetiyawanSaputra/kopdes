<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasRegionScope
{
    /**
     * Scope a query to only include records within the authenticated actor's region/branch scope.
     */
    public function scopeForUser(Builder $query, User $actor): Builder
    {
        if ($actor->role === User::ROLE_ADMIN_NASIONAL) {
            return $query;
        }

        $table = $this->getTable();
        $mode = $this->regionScopeMode();

        if ($mode === 'branch_self') {
            return match ($actor->role) {
                User::ROLE_ADMIN_PROVINSI => $query->where("{$table}.province_id", $actor->province_id),
                User::ROLE_ADMIN_KABUPATEN => $query->where("{$table}.regency_id", $actor->regency_id),
                User::ROLE_ADMIN_CABANG, User::ROLE_KASIR => $query->where("{$table}.id", $actor->branch_id),
                default => $query->whereRaw('1 = 0'),
            };
        }

        if ($mode === 'direct') {
            return match ($actor->role) {
                User::ROLE_ADMIN_PROVINSI => $query->where("{$table}.province_id", $actor->province_id),
                User::ROLE_ADMIN_KABUPATEN => $query->where("{$table}.regency_id", $actor->regency_id),
                User::ROLE_ADMIN_CABANG, User::ROLE_KASIR => $query->where("{$table}.branch_id", $actor->branch_id),
                default => $query->whereRaw('1 = 0'),
            };
        }

        $branchColumn = $this->regionBranchColumn();
        $branchRelation = $this->regionBranchRelation();

        return match ($actor->role) {
            User::ROLE_ADMIN_PROVINSI => $query->whereHas(
                $branchRelation,
                fn (Builder $b) => $b->where('branches.province_id', $actor->province_id)
            ),
            User::ROLE_ADMIN_KABUPATEN => $query->whereHas(
                $branchRelation,
                fn (Builder $b) => $b->where('branches.regency_id', $actor->regency_id)
            ),
            User::ROLE_ADMIN_CABANG, User::ROLE_KASIR => $query->where("{$table}.{$branchColumn}", $actor->branch_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Determine how region filtering is applied on this model:
     * - 'branch_self': for Branch model itself
     * - 'direct': model has direct province_id, regency_id, branch_id columns (e.g. User)
     * - 'via_branch': model scopes through a branch foreign key & relation
     */
    protected function regionScopeMode(): string
    {
        if ($this instanceof Branch) {
            return 'branch_self';
        }

        if ($this instanceof User) {
            return 'direct';
        }

        return 'via_branch';
    }

    /**
     * Foreign key column pointing to branches.id when using 'via_branch' mode.
     */
    protected function regionBranchColumn(): string
    {
        return 'branch_id';
    }

    /**
     * Eloquent relation name pointing to Branch when using 'via_branch' mode.
     */
    protected function regionBranchRelation(): string
    {
        return 'branch';
    }
}
