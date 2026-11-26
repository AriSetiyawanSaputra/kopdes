<?php

namespace App\Http\Requests;

class ActivityLogFilterRequest extends ReportFilterRequest
{
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
