<?php

namespace ShambaTrack\Core;

class Validation
{
    /**
     * Normalize a phone number to E.164 (+countrycode...).
     * Accepts common Tanzanian local formats (07XXXXXXXX, 06XXXXXXXX) and
     * assumes +255 when no country code is given, since v1's primary
     * audience is Tanzanian farmers — but any valid E.164 number passes
     * through unchanged, so the field isn't hard-locked to one country.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $trimmed = trim($raw);
        $digitsOnly = preg_replace('/[^\d+]/', '', $trimmed);

        if ($digitsOnly === '') {
            return null;
        }

        if (strpos($digitsOnly, '+') === 0) {
            $candidate = $digitsOnly;
        } elseif (preg_match('/^0\d{9}$/', $digitsOnly)) {
            // Local TZ format: 0712345678 -> +255712345678
            $candidate = '+255' . substr($digitsOnly, 1);
        } else {
            $candidate = '+' . $digitsOnly;
        }

        return self::isValidE164($candidate) ? $candidate : null;
    }

    public static function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }
}
