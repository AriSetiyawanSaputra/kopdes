<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MemberLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:64'],
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'Kode Member atau Nomor HP wajib diisi.',
            'pin.required' => 'PIN 6 digit wajib diisi.',
            'pin.regex' => 'PIN wajib terdiri dari tepat 6 digit angka.',
        ];
    }
}
