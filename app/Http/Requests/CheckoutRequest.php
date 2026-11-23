<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the authenticated cashier is authorized to perform a POS checkout.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->isCashier() || ! $user->is_active || ! $user->branch_id) {
            return false;
        }

        if (! $user->branch || ! $user->branch->is_active) {
            return false;
        }

        if ($this->hasSession() && (bool) $this->session()->get('pos_locked', false) === true) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the POS checkout request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'uuid'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'midtrans'])],
            'member_id' => [
                'nullable',
                'integer',
                Rule::exists('members', 'id')->where(
                    fn ($query) => $query->where('status', 'active')
                ),
            ],
            'cash_received' => [
                'nullable',
                'required_if:payment_method,cash',
                'numeric',
                'min:0',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1'],
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
            'idempotency_key.required' => 'Kunci idempotensi transaksi wajib disertakan.',
            'idempotency_key.uuid' => 'Format kunci idempotensi harus berupa UUID yang valid.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran harus berupa Tunai (cash) atau Midtrans (midtrans).',
            'member_id.exists' => 'Data member tidak ditemukan atau kartu member sedang tidak aktif.',
            'cash_received.required_if' => 'Nominal uang tunai diterima wajib diisi untuk pembayaran tunai.',
            'cash_received.numeric' => 'Nominal uang tunai diterima harus berupa angka.',
            'cash_received.min' => 'Nominal uang tunai diterima tidak boleh negatif.',
            'items.required' => 'Keranjang belanja tidak boleh kosong.',
            'items.array' => 'Format daftar item keranjang tidak valid.',
            'items.min' => 'Keranjang belanja minimal harus berisi 1 produk.',
            'items.*.product_id.required' => 'ID produk pada item keranjang wajib diisi.',
            'items.*.product_id.exists' => 'Salah satu produk di keranjang tidak ditemukan atau sedang tidak aktif.',
            'items.*.qty.required' => 'Kuantitas produk wajib diisi.',
            'items.*.qty.integer' => 'Kuantitas produk harus berupa angka bulat.',
            'items.*.qty.min' => 'Kuantitas produk minimal 1.',
        ];
    }
}
