<?php

namespace App\Http\Requests;

use App\Models\Member;
use App\Rules\UniqueCardUidAcrossActors;
use App\Support\CardUidNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceMemberCardRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('card_uid')) {
            $this->merge([
                'card_uid' => CardUidNormalizer::normalize((string) $this->input('card_uid')),
            ]);
        }
    }

    /**
     * Determine if the user is authorized to replace the member's RFID card.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var Member|null $member */
        $member = $this->route('member');

        return $actor !== null && $member !== null && $actor->can('update', $member);
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
            'card_uid' => [
                'required',
                'string',
                'max:64',
                new UniqueCardUidAcrossActors(ignoreMemberId: $member->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_uid.required' => 'UID kartu RFID baru wajib dipindai/diisi.',
        ];
    }
}
