<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Village;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request (RBAC + Anti-IDOR).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->can('create', Branch::class)) {
            return false;
        }

        if ($user->isAdminNasional()) {
            return true;
        }

        $inputProvinceId = $this->input('province_id');
        if ($inputProvinceId !== null && $inputProvinceId !== '' && $inputProvinceId !== $user->province_id) {
            return false;
        }

        $inputRegencyId = $this->input('regency_id');
        if ($inputRegencyId !== null && $inputRegencyId !== '' && $inputRegencyId !== $user->regency_id) {
            return false;
        }

        $villageId = $this->input('village_id');
        if (is_string($villageId) && $villageId !== '') {
            $village = Village::with('district.regency')->find($villageId);

            if ($village && $village->district && $village->district->regency) {
                return $user->can('create', [
                    Branch::class,
                    $village->district->regency_id,
                    $village->district->regency->province_id,
                ]);
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('branches', 'code')],
            'village_id' => [
                'required',
                'string',
                'size:10',
                Rule::exists('villages', 'id'),
                Rule::unique('branches', 'village_id'),
            ],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:25'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Custom validation messages in Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama cabang KOPDES wajib diisi.',
            'name.max' => 'Nama cabang KOPDES maksimal 150 karakter.',
            'code.unique' => 'Kode cabang ini sudah digunakan oleh cabang KOPDES lain.',
            'code.max' => 'Kode cabang maksimal 30 karakter.',
            'village_id.required' => 'Desa/Kelurahan wajib dipilih.',
            'village_id.size' => 'Kode desa harus tepat 10 karakter.',
            'village_id.exists' => 'Desa/Kelurahan yang dipilih tidak ditemukan dalam referensi wilayah.',
            'village_id.unique' => 'Desa ini sudah memiliki cabang KOPDES terdaftar.',
            'address.required' => 'Alamat lengkap cabang KOPDES wajib diisi.',
            'phone.max' => 'Nomor telepon maksimal 25 karakter.',
        ];
    }
}
