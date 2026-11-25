<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ReturnFilterRequest extends ReportFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'refund_method' => ['nullable', 'string', Rule::in(['all', 'cash_refund', 'manual_transfer'])],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'refund_method.in' => 'Filter metode refund harus berupa all, cash_refund, atau manual_transfer.',
        ]);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     payment_method: string,
     *     refund_method: string,
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
            'refund_method' => ! empty($validated['refund_method']) ? (string) $validated['refund_method'] : 'all',
        ]);
    }
}
