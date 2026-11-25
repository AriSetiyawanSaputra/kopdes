<?php

namespace App\Http\Requests;

use App\Models\CashierShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('open', CashierShift::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'opening_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Modal awal uang tunai di laci wajib diisi.',
            'opening_cash.numeric' => 'Modal awal laci harus berupa angka yang valid.',
            'opening_cash.min' => 'Modal awal laci tidak boleh bernilai negatif.',
            'opening_notes.max' => 'Catatan buka shift maksimal 500 karakter.',
        ];
    }
}
