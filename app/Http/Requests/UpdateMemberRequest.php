<?php

namespace App\Http\Requests;

use App\Models\Member;
use App\Rules\UniqueCardUidAcrossActors;
use App\Support\CardUidNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $actor = $this->user();
        /** @var Member|null $member */
        $member = $this->route('member');

        if ($this->filled('card_uid')) {
            $this->merge([
                'card_uid' => CardUidNormalizer::normalize((string) $this->input('card_uid')),
            ]);
        }

        if ($actor?->isAdminCabang()) {
            $this->merge([
                'home_branch_id' => $this->input('home_branch_id', $member?->home_branch_id ?? $actor->branch_id),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request (RBAC + Anti-IDOR).
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var Member|null $member */
        $member = $this->route('member');

        if (! $actor || ! $member || ! $actor->can('update', $member)) {
            return false;
        }

        if ($actor->isAdminCabang() && $this->filled('home_branch_id')) {
            if ((int) $this->input('home_branch_id') !== (int) $actor->branch_id) {
                return false;
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
        /** @var Member $member */
        $member = $this->route('member');

        return [
            'home_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'card_uid' => [
                'required',
                'string',
                'max:64',
                new UniqueCardUidAcrossActors(ignoreMemberId: $member->id),
            ],
            'phone' => ['nullable', 'string', 'max:25'],
            'nik' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('members', 'nik')->ignore($member->id),
            ],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'home_branch_id.required' => 'Cabang KOPDES asal wajib dipilih.',
            'name.required' => 'Nama lengkap anggota wajib diisi.',
            'card_uid.required' => 'Kartu RFID anggota wajib dipindai/diisi.',
            'nik.unique' => 'NIK ini sudah terdaftar pada anggota KOPDES lain.',
        ];
    }
}
