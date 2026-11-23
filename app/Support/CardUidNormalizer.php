<?php

namespace App\Support;

class CardUidNormalizer
{
    /**
     * Normalize raw RFID/NFC card UID input:
     * trim whitespace, strip non-alphanumeric characters, and convert to uppercase.
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $cleaned = preg_replace('/[^A-Za-z0-9]/', '', trim($raw));

        return strtoupper((string) $cleaned);
    }
}
