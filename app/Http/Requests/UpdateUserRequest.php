<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Regency;
use App\Models\User;
use App\Rules\UniqueCardUidAcrossActors;
use App\Support\CardUidNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $actor = $this->user();
        /** @var User|null $target */
        $target = $this->route('user');

        if ($this->filled('card_uid')) {
            $this->merge([
                'card_uid' => CardUidNormalizer::normalize((string) $this->input('card_uid')),
            ]);
        }

        if (! $this->filled('role') && $target) {
            $this->merge([
                'role' => $target->role,
            ]);
        }

        if ($actor?->isAdminCabang()) {
            $this->merge([
                'branch_id' => $this->input('branch_id', $actor->branch_id),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request (RBAC Hierarchy + Anti-IDOR).
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var User|null $target */
        $target = $this->route('user');

        if (! $actor || ! $target || ! $actor->can('update', $target)) {
            return false;
        }

        $targetRole = (string) $this->input('role', $target->role);
        $provinceId = $this->input('province_id', $target->province_id) ?: null;
        $regencyId = $this->input('regency_id', $target->regency_id) ?: null;
        $branchId = $this->filled('branch_id')
            ? (int) $this->input('branch_id')
            : ($target->branch_id ? (int) $target->branch_id : null);

        if ($branchId !== null) {
            $branch = Branch::find($branchId);
            if ($branch) {
                $provinceId = $branch->province_id;
                $regencyId = $branch->regency_id;
            }
        } elseif ($regencyId !== null) {
            $regency = Regency::find($regencyId);
            if ($regency) {
                $provinceId = $regency->province_id;
            }
        }

        return $actor->can('create', [
            User::class,
            $targetRole,
            $provinceId,
            $regencyId,
            $branchId,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $role = (string) $this->input('role', $target->role);
        $isCashier = $role === User::ROLE_KASIR;

        return [
            'name' => ['required', 'string', 'max:150'],
            'role' => [
                'required',
                'string',
                Rule::in([
                    User::ROLE_ADMIN_NASIONAL,
                    User::ROLE_ADMIN_PROVINSI,
                    User::ROLE_ADMIN_KABUPATEN,
                    User::ROLE_ADMIN_CABANG,
                    User::ROLE_KASIR,
                ]),
            ],
            'email' => [
                $isCashier ? 'nullable' : 'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
            ],
            'province_id' => [
                Rule::requiredIf($role === User::ROLE_ADMIN_PROVINSI),
                'nullable',
                'string',
                'size:2',
                Rule::exists('provinces', 'id'),
            ],
            'regency_id' => [
                Rule::requiredIf($role === User::ROLE_ADMIN_KABUPATEN),
                'nullable',
                'string',
                'size:4',
                Rule::exists('regencies', 'id'),
            ],
            'branch_id' => [
                Rule::requiredIf(in_array($role, [User::ROLE_ADMIN_CABANG, User::ROLE_KASIR], true)),
                'nullable',
                'integer',
                Rule::exists('branches', 'id'),
            ],
            'card_uid' => [
                Rule::requiredIf($isCashier),
                'nullable',
                'string',
                'max:64',
                new UniqueCardUidAcrossActors(ignoreUserId: $target->id),
            ],
            'pin' => [
                'nullable',
                'string',
                'regex:/^\d{6}$/',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi untuk akun Admin.',
            'email.unique' => 'Alamat email ini sudah digunakan oleh pengguna lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'province_id.required' => 'Provinsi wajib dipilih untuk Admin Provinsi.',
            'regency_id.required' => 'Kabupaten/Kota wajib dipilih untuk Admin Kabupaten.',
            'branch_id.required' => 'Cabang KOPDES wajib dipilih.',
            'card_uid.required' => 'Kartu RFID kasir wajib dipindai/diisi.',
            'pin.regex' => 'PIN Kasir wajib tepat 6 digit angka (0-9).',
        ];
    }
}
