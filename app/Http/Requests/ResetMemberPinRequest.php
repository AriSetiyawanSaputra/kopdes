<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetMemberPinRequest extends FormRequest
{
    /**
     * Only admin_nasional or admin_cabang of the member's home_branch_id may set/reset portal PIN.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var Member|null $member */
        $member = $this->route('member');

        if (! $actor || ! $member instanceof Member) {
            return false;
        }

        return $actor->can('update', $member);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{6}$/', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.required' => 'PIN Portal Member 6 digit wajib diisi.',
            'pin.regex' => 'PIN Portal Member wajib terdiri dari tepat 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN Portal Member tidak cocok.',
        ];
    }
}
