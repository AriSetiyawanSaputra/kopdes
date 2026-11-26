<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', AppSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'receipt_footer' => ['required', 'string', 'max:255'],
            'default_receipt_paper' => ['required', 'string', Rule::in(['58mm', '80mm'])],
            'return_max_days' => ['required', 'integer', 'min:1', 'max:90'],
            'shu_allocation_member_usaha' => ['required', 'numeric', 'min:0', 'max:100'],
            'shu_allocation_reserve' => ['required', 'numeric', 'min:0', 'max:100'],
            'shu_allocation_management' => ['required', 'numeric', 'min:0', 'max:100'],
            'shu_allocation_education' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'Nama lembaga koperasi wajib diisi.',
            'institution_name.max' => 'Nama lembaga maksimal 150 karakter.',
            'logo.mimes' => 'Logo koperasi harus berformat PNG, JPG, JPEG, atau WebP.',
            'logo.max' => 'Ukuran file logo koperasi maksimal 2 MB.',
            'receipt_footer.required' => 'Footer struk thermal wajib diisi.',
            'receipt_footer.max' => 'Footer struk thermal maksimal 255 karakter.',
            'default_receipt_paper.in' => 'Ukuran kertas struk default harus 58mm atau 80mm.',
            'return_max_days.integer' => 'Batas hari retur harus berupa bilangan bulat.',
            'return_max_days.min' => 'Batas hari retur minimal 1 hari.',
            'return_max_days.max' => 'Batas hari retur maksimal 90 hari.',
            'shu_allocation_member_usaha.required' => 'Persentase SHU Jasa Usaha Anggota wajib diisi.',
            'shu_allocation_reserve.required' => 'Persentase SHU Dana Cadangan wajib diisi.',
            'shu_allocation_management.required' => 'Persentase SHU Dana Pengurus & Pengawas wajib diisi.',
            'shu_allocation_education.required' => 'Persentase SHU Dana Pendidikan & Sosial wajib diisi.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = round(
                    (float) $this->input('shu_allocation_member_usaha', 0)
                    + (float) $this->input('shu_allocation_reserve', 0)
                    + (float) $this->input('shu_allocation_management', 0)
                    + (float) $this->input('shu_allocation_education', 0),
                    2
                );

                if (abs($total - 100.0) > 0.001) {
                    $validator->errors()->add(
                        'shu_allocation_member_usaha',
                        "Total penjumlahan 4 persentase alokasi SHU harus tepat 100% (saat ini {$total}%)."
                    );
                }
            },
        ];
    }
}
