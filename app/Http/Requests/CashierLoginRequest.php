<?php

namespace App\Http\Requests;

use App\Support\CardUidNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CashierLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('card_uid')) {
            $this->merge([
                'card_uid' => CardUidNormalizer::normalize((string) $this->input('card_uid')),
            ]);
        }
    }

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
            'card_uid' => ['required', 'string', 'max:64'],
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_uid.required' => 'Kartu RFID kasir wajib ditempelkan pada reader.',
            'pin.required' => 'PIN 6 digit kasir wajib diisi.',
            'pin.regex' => 'PIN kasir harus tepat 6 digit angka (0-9).',
        ];
    }
}
