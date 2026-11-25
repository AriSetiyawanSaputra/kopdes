<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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

        if ($this->has('member_price') && trim((string) $this->input('member_price')) === '') {
            $this->merge([
                'member_price' => null,
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'barcode' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'member_price' => ['nullable', 'numeric', 'min:0', 'lte:selling_price'],
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
            'member_price.numeric' => 'Harga khusus member harus berupa angka yang valid.',
            'member_price.min' => 'Harga khusus member tidak boleh bernilai negatif.',
            'member_price.lte' => 'Harga khusus member tidak boleh melebihi harga jual normal.',
        ];
    }
}
