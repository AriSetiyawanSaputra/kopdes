<?php

namespace App\Http\Requests;

use App\Models\CashierShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $shift = $this->route('shift');

        return $user !== null
            && $shift instanceof CashierShift
            && $user->can('recordMovement', $shift);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['cash_in', 'cash_out'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'notes' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis mutasi kas wajib dipilih.',
            'type.in' => 'Jenis mutasi kas harus berupa Kas Masuk (cash_in) atau Kas Keluar (cash_out).',
            'amount.required' => 'Nominal mutasi kas wajib diisi.',
            'amount.numeric' => 'Nominal mutasi kas harus berupa angka yang valid.',
            'amount.gt' => 'Nominal mutasi kas harus lebih besar dari Rp 0.',
            'notes.required' => 'Keterangan mutasi kas laci wajib diisi.',
            'notes.max' => 'Keterangan mutasi kas maksimal 255 karakter.',
        ];
    }
}
