<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    /**
     * Enforce RBAC & Anti-IDOR region parameter check via ReportPolicy::filterRegion.
     * Any attempt to pass province_id, regency_id, or branch_id outside the actor's hierarchy
     * returns HTTP 403 Forbidden (PRD 6.5 & AGENTS.md 4.2).
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor || ! $actor->is_active || ! $actor->isAdmin()) {
            return false;
        }

        $provinceId = $this->filled('province_id')
            ? trim((string) $this->input('province_id'))
            : null;
        $regencyId = $this->filled('regency_id')
            ? trim((string) $this->input('regency_id'))
            : null;
        $branchId = $this->filled('branch_id')
            ? (int) $this->input('branch_id')
            : null;

        return Gate::forUser($actor)->allows('filter-region', [
            $provinceId,
            $regencyId,
            $branchId,
        ]);
    }

    /**
     * Get the validation rules that apply to financial & member report filter parameters.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_method' => ['nullable', 'string', Rule::in(['all', 'cash', 'midtrans'])],
            'province_id' => ['nullable', 'string', 'size:2', Rule::exists('provinces', 'id')],
            'regency_id' => ['nullable', 'string', 'size:4', Rule::exists('regencies', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
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
            'start_date.date' => 'Format tanggal awal laporan tidak valid.',
            'end_date.date' => 'Format tanggal akhir laporan tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.',
            'payment_method.in' => 'Filter metode pembayaran harus all, cash, atau midtrans.',
            'province_id.exists' => 'Provinsi yang dipilih tidak ditemukan.',
            'regency_id.exists' => 'Kabupaten/Kota yang dipilih tidak ditemukan.',
            'branch_id.exists' => 'Cabang KOPDES yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * Return normalized filters with default "Bulan Ini" date range and role-locked region defaults.
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     payment_method: string,
     *     province_id: ?string,
     *     regency_id: ?string,
     *     branch_id: ?int,
     *     search: string
     * }
     */
    public function resolvedFilters(): array
    {
        $actor = $this->user();
        $validated = $this->validated();

        $startDate = ! empty($validated['start_date'])
            ? (string) $validated['start_date']
            : now()->startOfMonth()->toDateString();

        $endDate = ! empty($validated['end_date'])
            ? (string) $validated['end_date']
            : now()->endOfMonth()->toDateString();

        $paymentMethod = ! empty($validated['payment_method'])
            ? (string) $validated['payment_method']
            : 'all';

        $provinceId = ! empty($validated['province_id'])
            ? (string) $validated['province_id']
            : ($actor && ! $actor->isAdminNasional() ? $actor->province_id : null);

        $regencyId = ! empty($validated['regency_id'])
            ? (string) $validated['regency_id']
            : ($actor && ($actor->isAdminKabupaten() || $actor->isAdminCabang()) ? $actor->regency_id : null);

        $branchId = isset($validated['branch_id']) && $validated['branch_id'] !== ''
            ? (int) $validated['branch_id']
            : ($actor && $actor->isAdminCabang() ? (int) $actor->branch_id : null);

        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_method' => $paymentMethod,
            'province_id' => $provinceId,
            'regency_id' => $regencyId,
            'branch_id' => $branchId,
            'search' => $search,
        ];
    }
}
