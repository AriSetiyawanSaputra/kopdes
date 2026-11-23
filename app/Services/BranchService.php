<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Village;
use Illuminate\Support\Facades\DB;

class BranchService
{
    /**
     * Create a new KOPDES branch with server-side region derivation from village_id
     * and automatic branch code generation if not provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $village = Village::with('district.regency')->findOrFail($data['village_id']);
            $district = $village->district;
            $regency = $district->regency;

            $rawCode = isset($data['code']) ? trim((string) $data['code']) : '';
            $code = $rawCode !== ''
                ? strtoupper($rawCode)
                : $this->generateBranchCode($regency->id);

            return Branch::create([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'village_id' => $village->id,
                'district_id' => $district->id,
                'regency_id' => $regency->id,
                'province_id' => $regency->province_id,
                'address' => trim((string) $data['address']),
                'phone' => isset($data['phone']) && trim((string) $data['phone']) !== ''
                    ? trim((string) $data['phone'])
                    : null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
            ]);
        });
    }

    /**
     * Update an existing KOPDES branch with server-side region derivation from village_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data): Branch {
            $village = Village::with('district.regency')->findOrFail($data['village_id']);
            $district = $village->district;
            $regency = $district->regency;

            $rawCode = isset($data['code']) ? trim((string) $data['code']) : '';
            $code = $rawCode !== ''
                ? strtoupper($rawCode)
                : ($branch->regency_id === $regency->id ? $branch->code : $this->generateBranchCode($regency->id));

            $branch->update([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'village_id' => $village->id,
                'district_id' => $district->id,
                'regency_id' => $regency->id,
                'province_id' => $regency->province_id,
                'address' => trim((string) $data['address']),
                'phone' => isset($data['phone']) && trim((string) $data['phone']) !== ''
                    ? trim((string) $data['phone'])
                    : null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $branch->is_active,
            ]);

            // Keep branch-bound users' denormalized province_id & regency_id synchronized
            $branch->users()->update([
                'province_id' => $regency->province_id,
                'regency_id' => $regency->id,
            ]);

            return $branch->refresh();
        });
    }

    /**
     * Toggle operational active status of a KOPDES branch.
     */
    public function toggleActive(Branch $branch): Branch
    {
        $branch->update([
            'is_active' => ! $branch->is_active,
        ]);

        return $branch->refresh();
    }

    /**
     * Generate unique branch code with format KOP-{REGENCY_ID}-{SEQUENCE_3_DIGIT}.
     */
    public function generateBranchCode(string $regencyId): string
    {
        $prefix = "KOP-{$regencyId}-";

        $existingCodes = Branch::query()
            ->where('code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('code');

        $maxSeq = 0;

        foreach ($existingCodes as $existingCode) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $existingCode, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
            $exists = Branch::where('code', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }
}
