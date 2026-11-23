<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * List provinces accessible within the authenticated user's region scope.
     */
    public function provinces(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Province::query()->orderBy('name');

        if (! $user->isAdminNasional()) {
            $query->where('id', $user->province_id);
        }

        return response()->json([
            'data' => $query->get(['id', 'name']),
        ]);
    }

    /**
     * List regencies filtered by province_id and constrained to the user's region scope.
     */
    public function regencies(Request $request, ?string $provinceId = null): JsonResponse
    {
        $user = $request->user();
        $provinceId = $provinceId ?? $request->query('province_id');

        if ($provinceId !== null && $provinceId !== '' && ! $user->isAdminNasional() && $provinceId !== $user->province_id) {
            abort(403, 'Anda tidak memiliki akses ke provinsi di luar wilayah Anda.');
        }

        $query = Regency::query()->orderBy('name');

        if ($user->isAdminNasional()) {
            if ($provinceId) {
                $query->where('province_id', $provinceId);
            }
        } elseif ($user->isAdminProvinsi()) {
            $query->where('province_id', $user->province_id);
        } else {
            $query->where('id', $user->regency_id);
        }

        return response()->json([
            'data' => $query->get(['id', 'province_id', 'name']),
        ]);
    }

    /**
     * List districts filtered by regency_id and constrained to the user's region scope.
     */
    public function districts(Request $request, ?string $regencyId = null): JsonResponse
    {
        $user = $request->user();
        $regencyId = $regencyId ?? $request->query('regency_id');

        if (! $regencyId) {
            return response()->json(['data' => []]);
        }

        $regency = Regency::find($regencyId);

        if (! $regency) {
            return response()->json(['data' => []]);
        }

        if ($user->isAdminProvinsi() && $regency->province_id !== $user->province_id) {
            abort(403, 'Anda tidak memiliki akses ke kabupaten di luar provinsi Anda.');
        }

        if (($user->isAdminKabupaten() || $user->isAdminCabang()) && $regency->id !== $user->regency_id) {
            abort(403, 'Anda tidak memiliki akses ke kabupaten di luar wilayah Anda.');
        }

        $query = District::query()
            ->where('regency_id', $regency->id)
            ->orderBy('name');

        if ($user->isAdminCabang() && $user->branch) {
            $query->where('id', $user->branch->district_id);
        }

        return response()->json([
            'data' => $query->get(['id', 'regency_id', 'name']),
        ]);
    }

    /**
     * List villages filtered by district_id and constrained to the user's region scope.
     * Includes existing branch info so UI can indicate whether a village already has a KOPDES.
     */
    public function villages(Request $request, ?string $districtId = null): JsonResponse
    {
        $user = $request->user();
        $districtId = $districtId ?? $request->query('district_id');

        if (! $districtId) {
            return response()->json(['data' => []]);
        }

        $district = District::with('regency')->find($districtId);

        if (! $district || ! $district->regency) {
            return response()->json(['data' => []]);
        }

        if ($user->isAdminProvinsi() && $district->regency->province_id !== $user->province_id) {
            abort(403, 'Anda tidak memiliki akses ke kecamatan di luar provinsi Anda.');
        }

        if (($user->isAdminKabupaten() || $user->isAdminCabang()) && $district->regency_id !== $user->regency_id) {
            abort(403, 'Anda tidak memiliki akses ke kecamatan di luar kabupaten Anda.');
        }

        $query = Village::query()
            ->with(['branch:id,code,name,village_id'])
            ->where('district_id', $district->id)
            ->orderBy('name');

        if ($user->isAdminCabang() && $user->branch) {
            $query->where('id', $user->branch->village_id);
        }

        $villages = $query->get(['id', 'district_id', 'name'])->map(fn (Village $village) => [
            'id' => $village->id,
            'district_id' => $village->district_id,
            'name' => $village->name,
            'has_branch' => $village->branch !== null,
            'branch_id' => $village->branch?->id,
            'branch_code' => $village->branch?->code,
            'branch_name' => $village->branch?->name,
        ]);

        return response()->json([
            'data' => $villages,
        ]);
    }
}
