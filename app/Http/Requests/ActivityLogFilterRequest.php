<?php

namespace App\Http\Requests;

use App\Models\User;

class ActivityLogFilterRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        // SEC-12: Tolak actor_id di luar scope wilayah/cabang pengguna dengan HTTP 403
        if ($this->filled('actor_id')) {
            $requestedActorId = (int) $this->input('actor_id');
            $isWithinScope = User::forUser($actor)
                ->whereKey($requestedActorId)
                ->exists();

            if (! $isWithinScope) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'action' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     payment_method: string,
     *     province_id: ?string,
     *     regency_id: ?string,
     *     branch_id: ?int,
     *     search: string,
     *     action: string,
     *     actor_id: ?int
     * }
     */
    public function resolvedFilters(): array
    {
        $base = parent::resolvedFilters();
        $validated = $this->validated();

        return array_merge($base, [
            'action' => ! empty($validated['action']) ? trim((string) $validated['action']) : '',
            'actor_id' => ! empty($validated['actor_id']) ? (int) $validated['actor_id'] : null,
        ]);
    }
}
