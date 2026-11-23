<?php

namespace App\Rules;

use App\Models\Member;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueCardUidAcrossActors implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreUserId = null,
        private readonly ?int $ignoreMemberId = null,
    ) {}

    /**
     * Run the validation rule: normalize card_uid and ensure uniqueness across both users and members tables.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = CardUidNormalizer::normalize(is_scalar($value) ? (string) $value : null);

        if ($normalized === '') {
            $fail('UID kartu RFID tidak valid (wajib berisi karakter alfanumerik).');

            return;
        }

        if (strlen($normalized) > 64) {
            $fail('UID kartu RFID maksimal 64 karakter alfanumerik.');

            return;
        }

        $usedByUser = User::query()
            ->where('card_uid', $normalized)
            ->when($this->ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreUserId))
            ->exists();

        if ($usedByUser) {
            $fail('Kartu RFID ini sudah terdaftar dan digunakan oleh akun Kasir.');

            return;
        }

        $usedByMember = Member::query()
            ->where('card_uid', $normalized)
            ->when($this->ignoreMemberId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreMemberId))
            ->exists();

        if ($usedByMember) {
            $fail('Kartu RFID ini sudah terdaftar dan digunakan oleh Anggota (Member) KOPDES.');
        }
    }
}
