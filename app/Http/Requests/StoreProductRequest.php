<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('sku')) {
            $this->merge([
                'sku' => strtoupper(trim((string) $this->input('sku'))),
            ]);
        }

        if ($this->filled('barcode')) {
            $this->merge([
                'barcode' => trim((string) $this->input('barcode')),
            ]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->is_active && $user->isAdminNasional();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')],
            'barcode' => ['required', 'string', 'max:64', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori produk wajib dipilih.',
            'category_id.exists' => 'Kategori produk tidak valid.',
            'sku.required' => 'Kode SKU produk wajib diisi.',
            'sku.unique' => 'Kode SKU ini sudah digunakan oleh produk lain.',
            'barcode.required' => 'Kode Barcode produk wajib diisi.',
            'barcode.unique' => 'Kode Barcode ini sudah digunakan oleh produk lain.',
            'name.required' => 'Nama produk wajib diisi.',
            'unit.required' => 'Satuan jual produk wajib diisi.',
            'cost_price.required' => 'Harga Pokok Penjualan (HPP) wajib diisi.',
            'cost_price.min' => 'HPP tidak boleh bernilai negatif.',
            'selling_price.required' => 'Harga jual nasional wajib diisi.',
            'selling_price.min' => 'Harga jual tidak boleh bernilai negatif.',
        ];
    }
}
