<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Models\TransactionReturn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionReturnRequest extends FormRequest
{
    /**
     * Enforce RBAC & Anti-IDOR eksklusif Dashboard Admin (admin_cabang cabang sendiri / admin_nasional).
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->is_active) {
            return false;
        }

        $transactionId = $this->filled('transaction_id') ? (int) $this->input('transaction_id') : null;
        $transaction = $transactionId ? Transaction::find($transactionId) : null;

        return $actor->can('create', [TransactionReturn::class, $transaction]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'integer', Rule::exists('transactions', 'id')],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'refund_method' => ['required', 'string', Rule::in(['cash_refund', 'manual_transfer'])],
            'cash_source' => [
                'nullable',
                'required_if:refund_method,cash_refund',
                Rule::in(['branch_cash', 'active_shift']),
            ],
            'refund_cashier_shift_id' => [
                'nullable',
                'required_if:cash_source,active_shift',
                'integer',
                Rule::exists('cashier_shifts', 'id'),
            ],
            'refund_reference' => [
                'nullable',
                'required_if:refund_method,manual_transfer',
                'string',
                'min:3',
                'max:120',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_item_id' => ['required', 'integer', Rule::exists('transaction_items', 'id')],
            'items.*.qty_returned' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'string', Rule::in(['restockable', 'damaged'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_id.required' => 'Transaksi asal wajib dipilih.',
            'transaction_id.exists' => 'Transaksi asal tidak ditemukan.',
            'reason.required' => 'Alasan retur atau pembatalan transaksi wajib diisi.',
            'reason.min' => 'Alasan retur minimal 3 karakter.',
            'refund_method.required' => 'Metode pengembalian dana wajib dipilih.',
            'refund_method.in' => 'Metode refund harus berupa Tunai (cash_refund) atau Transfer Manual (manual_transfer).',
            'cash_source.required_if' => 'Sumber dana tunai wajib dipilih untuk pengembalian tunai.',
            'cash_source.in' => 'Sumber dana tunai harus berupa Kas Cabang atau Laci Shift Aktif.',
            'refund_cashier_shift_id.required_if' => 'Shift kasir aktif wajib dipilih apabila refund diambil dari laci shift.',
            'refund_cashier_shift_id.exists' => 'Shift kasir yang dipilih tidak ditemukan.',
            'refund_reference.required_if' => 'Nomor referensi / keterangan transfer wajib diisi untuk Transfer Manual.',
            'refund_reference.min' => 'Nomor referensi transfer minimal 3 karakter.',
            'items.required' => 'Minimal pilih 1 item barang untuk diretur.',
            'items.min' => 'Minimal pilih 1 item barang untuk diretur.',
            'items.*.transaction_item_id.required' => 'ID item transaksi asal wajib disertakan.',
            'items.*.qty_returned.required' => 'Jumlah retur tiap item wajib diisi.',
            'items.*.qty_returned.min' => 'Jumlah retur minimal 1 unit.',
            'items.*.condition.required' => 'Kondisi barang retur wajib dipilih.',
            'items.*.condition.in' => 'Kondisi barang harus Layak Jual (restockable) atau Rusak/Cacat (damaged).',
        ];
    }
}
