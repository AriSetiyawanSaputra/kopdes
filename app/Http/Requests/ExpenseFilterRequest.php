<?php

namespace App\Http\Requests;

use App\Models\OperationalExpense;
use Illuminate\Validation\Rule;

class ExpenseFilterRequest extends ReportFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => [
                'nullable',
                'string',
                Rule::in(array_merge(['all'], array_keys(OperationalExpense::CATEGORIES))),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'category.in' => 'Kategori biaya operasional yang dipilih tidak valid.',
        ]);
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     payment_method: string,
     *     category: string,
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
            'category' => ! empty($validated['category'])
                ? (string) $validated['category']
                : 'all',
        ]);
    }
}
