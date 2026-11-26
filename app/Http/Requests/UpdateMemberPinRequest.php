<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdateMemberPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') instanceof Member;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'new_pin' => ['required', 'string', 'regex:/^\d{6}$/', 'different:current_pin', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('current_pin')) {
                return;
            }

            /** @var Member|null $member */
            $member = $this->user('member');
            $currentPin = (string) $this->input('current_pin', '');

            if (! $member || empty($member->pin_hash) || ! Hash::check($currentPin, $member->pin_hash)) {
                $validator->errors()->add('current_pin', 'PIN lama yang Anda masukkan tidak sesuai.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_pin.required' => 'PIN lama wajib diisi.',
            'current_pin.regex' => 'PIN lama harus terdiri dari 6 digit angka.',
            'new_pin.required' => 'PIN baru wajib diisi.',
            'new_pin.regex' => 'PIN baru harus terdiri dari tepat 6 digit angka.',
            'new_pin.different' => 'PIN baru harus berbeda dari PIN lama Anda.',
            'new_pin.confirmed' => 'Konfirmasi PIN baru tidak cocok.',
        ];
    }
}
