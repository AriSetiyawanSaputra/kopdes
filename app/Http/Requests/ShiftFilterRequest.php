<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ShiftFilterRequest extends ReportFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['nullable', 'string', Rule::in(['all', 'open', 'closed'])],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.in' => 'Filter status shift harus berupa all, open, atau closed.',
        ]);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     payment_method: string,
     *     status: string,
     *     province_id: ?string,
     *     regency_id: ?string,
     *     branch_id: ?int,
     *     search: string
     * }
     */
    public function resolvedFilters(): array
    {
        $base = parent::resolvedFilters();
        $validated = $this->validated();

        return array_merge($base, [
            'status' => ! empty($validated['status']) ? (string) $validated['status'] : 'all',
        ]);
    }
}
