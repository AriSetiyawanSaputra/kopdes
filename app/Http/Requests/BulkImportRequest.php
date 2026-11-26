<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->is_active) {
            return false;
        }

        $type = (string) $this->route('type');

        if ($type === 'products') {
            return $actor->isAdminNasional();
        }

        if (in_array($type, ['stocks', 'members'], true)) {
            if ($actor->isAdminNasional()) {
                return true;
            }

            if ($actor->isAdminCabang()) {
                // Anti-IDOR: admin_cabang tidak boleh menyuntikkan branch_id cabang lain
                if ($this->filled('branch_id') && (int) $this->input('branch_id') !== (int) $actor->branch_id) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $type = (string) $this->route('type');

        $branchRules = ['nullable', 'integer', Rule::exists('branches', 'id')];
        if (in_array($type, ['stocks', 'members'], true) && $actor?->isAdminNasional()) {
            $branchRules = ['required', 'integer', Rule::exists('branches', 'id')];
        }

        return [
            'file' => ['required', 'file', 'max:5120'],
            'branch_id' => $branchRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File Excel (.xlsx) atau CSV (.csv) wajib dipilih.',
            'file.max' => 'Ukuran file import maksimal 5 MB.',
            'branch_id.required' => 'Cabang KOPDES target wajib dipilih.',
            'branch_id.exists' => 'Cabang KOPDES target tidak valid.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');
                if (! $file) {
                    return;
                }

                $ext = strtolower((string) $file->getClientOriginalExtension());
                if (! in_array($ext, ['xlsx', 'csv'], true)) {
                    $validator->errors()->add('file', 'Format file wajib berupa Excel (.xlsx) atau CSV (.csv).');
                }
            },
        ];
    }
}
