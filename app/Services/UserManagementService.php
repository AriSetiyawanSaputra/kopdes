<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Regency;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    /**
     * Create a new Admin or Cashier user while strictly enforcing scope column nullability rules (PRD 2.1).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $role = (string) $data['role'];
            $scope = $this->resolveScopeColumns($actor, $role, $data);

            $isCashier = $role === User::ROLE_KASIR;

            return User::create([
                'name' => trim((string) $data['name']),
                'email' => ! empty($data['email']) ? strtolower(trim((string) $data['email'])) : null,
                'password' => ! $isCashier && ! empty($data['password'])
                    ? Hash::make((string) $data['password'])
                    : null,
                'role' => $role,
                'province_id' => $scope['province_id'],
                'regency_id' => $scope['regency_id'],
                'branch_id' => $scope['branch_id'],
                'card_uid' => $isCashier && ! empty($data['card_uid'])
                    ? CardUidNormalizer::normalize((string) $data['card_uid'])
                    : null,
                'pin_hash' => $isCashier && ! empty($data['pin'])
                    ? Hash::make((string) $data['pin'])
                    : null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
            ]);
        });
    }

    /**
     * Update an existing Admin or Cashier user while preserving scope consistency and hashed credentials.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data): User {
            $role = (string) ($data['role'] ?? $target->role);
            $scope = $this->resolveScopeColumns($actor, $role, $data);

            $isCashier = $role === User::ROLE_KASIR;

            $payload = [
                'name' => trim((string) $data['name']),
                'email' => ! empty($data['email']) ? strtolower(trim((string) $data['email'])) : null,
                'role' => $role,
                'province_id' => $scope['province_id'],
                'regency_id' => $scope['regency_id'],
                'branch_id' => $scope['branch_id'],
                'card_uid' => $isCashier && ! empty($data['card_uid'])
                    ? CardUidNormalizer::normalize((string) $data['card_uid'])
                    : null,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $target->is_active,
            ];

            if ($isCashier) {
                $payload['password'] = null;

                if (! empty($data['pin'])) {
                    $payload['pin_hash'] = Hash::make((string) $data['pin']);
                }
            } else {
                $payload['pin_hash'] = null;

                if (! empty($data['password'])) {
                    $payload['password'] = Hash::make((string) $data['password']);
                }
            }

            $target->update($payload);

            return $target->refresh();
        });
    }

    /**
     * Toggle active status of a user account.
     */
    public function toggleActive(User $target): User
    {
        $target->update([
            'is_active' => ! $target->is_active,
        ]);

        return $target->refresh();
    }

    /**
     * Derive province_id, regency_id, and branch_id on the server based on target role (PRD Table 2.1).
     *
     * @param  array<string, mixed>  $data
     * @return array{province_id: ?string, regency_id: ?string, branch_id: ?int}
     */
    private function resolveScopeColumns(User $actor, string $role, array $data): array
    {
        if ($role === User::ROLE_ADMIN_NASIONAL) {
            return [
                'province_id' => null,
                'regency_id' => null,
                'branch_id' => null,
            ];
        }

        if ($role === User::ROLE_ADMIN_PROVINSI) {
            return [
                'province_id' => (string) $data['province_id'],
                'regency_id' => null,
                'branch_id' => null,
            ];
        }

        if ($role === User::ROLE_ADMIN_KABUPATEN) {
            $regency = Regency::findOrFail($data['regency_id']);

            return [
                'province_id' => $regency->province_id,
                'regency_id' => $regency->id,
                'branch_id' => null,
            ];
        }

        // ROLE_ADMIN_CABANG or ROLE_KASIR
        $branchId = $actor->isAdminCabang()
            ? (int) $actor->branch_id
            : (int) $data['branch_id'];

        $branch = Branch::findOrFail($branchId);

        return [
            'province_id' => $branch->province_id,
            'regency_id' => $branch->regency_id,
            'branch_id' => $branch->id,
        ];
    }
}
