<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\BranchStock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $actor = $this->user();

        if ($actor?->isAdminCabang() && ! $this->filled('branch_id')) {
            $this->merge([
                'branch_id' => $actor->branch_id,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request (RBAC + Anti-IDOR).
     * Only active admin_cabang can adjust inventory on their own branch.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor) {
            return false;
        }

        $routeBranch = $this->route('branch');
        if ($routeBranch instanceof Branch) {
            return $actor->can('adjust', [BranchStock::class, $routeBranch]);
        }

        if ($this->filled('branch_id')) {
            $branchId = (int) $this->input('branch_id');

            if ((int) $actor->branch_id !== $branchId) {
                return false;
            }

            $branch = Branch::find($branchId);

            if (! $branch) {
                return false;
            }

            return $actor->can('adjust', [BranchStock::class, $branch]);
        }

        return $actor->can('adjust', BranchStock::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['sometimes', 'integer', Rule::exists('branches', 'id')],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'actual_quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['required', 'string', 'max:1000'],
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
            'branch_id.exists' => 'Cabang KOPDES tidak ditemukan.',
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.integer' => 'ID produk tidak valid.',
            'product_id.exists' => 'Produk tidak ditemukan atau sedang tidak aktif.',
            'actual_quantity.required' => 'Stok fisik aktual wajib diisi.',
            'actual_quantity.integer' => 'Stok fisik aktual harus berupa angka bulat.',
            'actual_quantity.min' => 'Stok fisik aktual tidak boleh kurang dari 0.',
            'notes.required' => 'Catatan alasan penyesuaian stok wajib diisi.',
            'notes.string' => 'Catatan alasan penyesuaian stok harus berupa teks.',
            'notes.max' => 'Catatan alasan penyesuaian stok maksimal 1000 karakter.',
        ];
    }
}
