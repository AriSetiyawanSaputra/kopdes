<?php

namespace App\Http\Requests;

use App\Models\OperationalExpense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalExpenseRequest extends FormRequest
{
    /**
     * Enforce RBAC & Anti-IDOR untuk pembuatan biaya operasional baru.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->is_active) {
            return false;
        }

        $targetBranchId = $this->filled('branch_id')
            ? (int) $this->input('branch_id')
            : null;

        return $actor->can('create', [OperationalExpense::class, $targetBranchId]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $branchRules = $actor && $actor->isAdminNasional()
            ? ['required', 'integer', Rule::exists('branches', 'id')]
            : ['nullable', 'integer', Rule::exists('branches', 'id')];

        return [
            'branch_id' => $branchRules,
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', Rule::in(array_keys(OperationalExpense::CATEGORIES))],
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Cabang KOPDES wajib dipilih.',
            'branch_id.exists' => 'Cabang KOPDES yang dipilih tidak ditemukan.',
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.date' => 'Format tanggal pengeluaran tidak valid.',
            'category.required' => 'Kategori biaya operasional wajib dipilih.',
            'category.in' => 'Kategori biaya operasional tidak valid.',
            'description.required' => 'Deskripsi pengeluaran wajib diisi.',
            'description.max' => 'Deskripsi pengeluaran maksimal 200 karakter.',
            'amount.required' => 'Nominal biaya wajib diisi.',
            'amount.numeric' => 'Nominal biaya harus berupa angka yang valid.',
            'amount.gt' => 'Nominal biaya harus lebih besar dari Rp 0.',
            'amount.max' => 'Nominal biaya melebihi batas maksimal yang diizinkan.',
            'notes.max' => 'Catatan tambahan maksimal 1.000 karakter.',
        ];
    }
}
