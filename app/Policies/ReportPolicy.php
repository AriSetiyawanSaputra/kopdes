<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Regency;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the actor can view the admin financial dashboard.
     */
    public function viewDashboard(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Determine whether the actor can view the sales & gross profit report.
     */
    public function viewSalesReport(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Determine whether the actor can view the member transaction recap report.
     */
    public function viewMemberReport(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Validate that region/branch query filter parameters do not violate the actor's scope (anti-IDOR).
     * Returns false (triggering HTTP 403 Forbidden) if any parameter is outside the user's hierarchy.
     */
    public function filterRegion(
        User $actor,
        ?string $provinceId = null,
        ?string $regencyId = null,
        ?int $branchId = null
    ): bool {
        if (! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isAdminNasional()) {
            return true;
        }

        if ($provinceId !== null && $provinceId !== '' && $provinceId !== $actor->province_id) {
            return false;
        }

        if ($regencyId !== null && $regencyId !== '') {
            if ($actor->isAdminProvinsi()) {
                $regency = Regency::find($regencyId);

                if (! $regency || $regency->province_id !== $actor->province_id) {
                    return false;
                }
            } elseif ($regencyId !== $actor->regency_id) {
                return false;
            }
        }

        if ($branchId !== null) {
            $branch = Branch::find($branchId);

            if (! $branch) {
                return false;
            }

            if ($actor->isAdminProvinsi() && $branch->province_id !== $actor->province_id) {
                return false;
            }

            if ($actor->isAdminKabupaten() && $branch->regency_id !== $actor->regency_id) {
                return false;
            }

            if ($actor->isAdminCabang() && (int) $branch->id !== (int) $actor->branch_id) {
                return false;
            }
        }

        return true;
    }
}
