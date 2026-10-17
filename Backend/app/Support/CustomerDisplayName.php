<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Builds a privacy-safe masked display name (e.g. "مح****مد", "بر****اء").
 */
final class CustomerDisplayName
{
    public static function from(?Customer $customer): string
    {
        if ($customer === null) {
            return 'عميل';
        }

        return self::mask((string) $customer->name);
    }

    public static function mask(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'عميل';
        }

        if (str_contains($name, '*')) {
            return $name;
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return 'عميل';
        }

        $maskedParts = array_map([self::class, 'maskWord'], $parts);

        return implode(' ', $maskedParts);
    }

    public static function maskWord(string $word): string
    {
        $len = mb_strlen($word);
        if ($len <= 2) {
            return mb_substr($word, 0, 1).'***';
        }

        if ($len === 3) {
            return mb_substr($word, 0, 1).'***'.mb_substr($word, -1);
        }

        // For length >= 4: keep first 2 chars, insert '****', keep last 2 chars
        $start = mb_substr($word, 0, 2);
        $end = mb_substr($word, -2);

        return $start.'****'.$end;
    }
}
