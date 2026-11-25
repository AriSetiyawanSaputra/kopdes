<?php

namespace App\Http\Requests;

use App\Models\CashierShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForceCloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $shift = $this->route('shift');

        return $user !== null
            && $shift instanceof CashierShift
            && $user->can('forceClose', $shift);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_closing_cash' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'closing_notes' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actual_closing_cash.required' => 'Nominal uang tunai fisik wajib diisi untuk menutup paksa shift.',
            'actual_closing_cash.numeric' => 'Nominal uang tunai fisik harus berupa angka yang valid.',
            'actual_closing_cash.min' => 'Nominal uang tunai fisik tidak boleh bernilai negatif.',
            'closing_notes.required' => 'Alasan penutupan paksa (force-close) shift wajib diisi.',
            'closing_notes.min' => 'Alasan force-close minimal 3 karakter.',
            'closing_notes.max' => 'Catatan force-close maksimal 500 karakter.',
        ];
    }
}
