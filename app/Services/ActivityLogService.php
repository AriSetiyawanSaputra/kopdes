<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogService
{
    /**
     * Sensitive field names that must NEVER be stored in old_values or new_values (PRD_PHASE3 §6.4).
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'pin',
        'pin_confirmation',
        'current_pin',
        'new_pin',
        'new_pin_confirmation',
        'pin_hash',
        'remember_token',
        'server_key',
        'client_key',
        'secret',
        'api_token',
    ];

    /**
     * Record an immutable activity log entry with automatic sensitive-field redaction
     * and hierarchical region scope derivation.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array{province_id?: ?string, regency_id?: ?string, branch_id?: ?int}|null  $regionOverride
     */
    public function record(
        string $action,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $regionOverride = null,
    ): ActivityLog {
        $resolvedActor = $actor ?? auth('web')->user();
        $scope = $regionOverride !== null
            ? [
                'province_id' => $regionOverride['province_id'] ?? null,
                'regency_id' => $regionOverride['regency_id'] ?? null,
                'branch_id' => isset($regionOverride['branch_id']) ? (int) $regionOverride['branch_id'] : null,
            ]
            : $this->resolveRegionScope($subject, $resolvedActor);

        $sanitizedOld = $this->sanitize($oldValues);
        $sanitizedNew = $this->sanitize($newValues);

        $request = app()->bound('request') ? request() : null;
        $ipAddress = $request?->ip();
        $userAgent = $request?->userAgent();

        return ActivityLog::create([
            'actor_id' => $resolvedActor?->id,
            'actor_role' => $resolvedActor?->role,
            'action' => $action,
            'description' => Str::limit(trim($description), 255, ''),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'province_id' => $scope['province_id'],
            'regency_id' => $scope['regency_id'],
            'branch_id' => $scope['branch_id'],
            'old_values' => $sanitizedOld,
            'new_values' => $sanitizedNew,
            'ip_address' => $ipAddress ? Str::limit($ipAddress, 45, '') : null,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively strip sensitive keys from payload arrays so credentials/secrets never leak into activity_logs.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $val) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($val)) {
                $clean[$key] = $this->sanitize($val);
            } else {
                $clean[$key] = $val;
            }
        }

        return $clean;
    }

    private function isSensitiveKey(string $normalizedKey): bool
    {
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['password', 'pin_hash', 'remember_token', 'server_key'] as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return $normalizedKey === 'pin';
    }

    /**
     * Derive province_id, regency_id, and branch_id from the target subject or actor.
     *
     * @return array{province_id: ?string, regency_id: ?string, branch_id: ?int}
     */
    private function resolveRegionScope(?Model $subject, ?User $actor): array
    {
        if ($subject instanceof Branch) {
            return [
                'province_id' => $subject->province_id,
                'regency_id' => $subject->regency_id,
                'branch_id' => $subject->id,
            ];
        }

        if ($subject instanceof Member) {
            $subject->loadMissing('homeBranch:id,province_id,regency_id');
            $branch = $subject->homeBranch;

            return [
                'province_id' => $branch?->province_id,
                'regency_id' => $branch?->regency_id,
                'branch_id' => $subject->home_branch_id,
            ];
        }

        if ($subject instanceof User) {
            return [
                'province_id' => $subject->province_id,
                'regency_id' => $subject->regency_id,
                'branch_id' => $subject->branch_id,
            ];
        }

        if ($subject !== null && $subject->getAttribute('branch_id') !== null) {
            $branch = method_exists($subject, 'branch')
                ? ($subject->relationLoaded('branch') ? $subject->getRelation('branch') : $subject->branch()->first(['id', 'province_id', 'regency_id']))
                : Branch::find($subject->getAttribute('branch_id'), ['id', 'province_id', 'regency_id']);

            if ($branch instanceof Branch) {
                return [
                    'province_id' => $branch->province_id,
                    'regency_id' => $branch->regency_id,
                    'branch_id' => $branch->id,
                ];
            }
        }

        if ($actor !== null && ! $actor->isAdminNasional()) {
            return [
                'province_id' => $actor->province_id,
                'regency_id' => $actor->regency_id,
                'branch_id' => $actor->branch_id,
            ];
        }

        return [
            'province_id' => null,
            'regency_id' => null,
            'branch_id' => null,
        ];
    }
}
