<?php

namespace App\Support;

final class PhoneNormalizer
{
    /**
     * Convert Eastern Arabic numerals to standard Arabic/Latin numerals.
     */
    public static function convertArabicDigits(string $string): string
    {
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $standardDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $string = str_replace($arabicDigits, $standardDigits, $string);

        return str_replace($persianDigits, $standardDigits, $string);
    }

    /**
     * Normalize a phone number to standard E.164 format.
     */
    public static function normalize(?string $phone, ?string $defaultCallingCode = null): ?string
    {
        if ($phone === null) {
            return null;
        }

        $callingCode = self::normalizeCallingCode($defaultCallingCode ?? CountryCallingCode::default());

        $cleaned = self::convertArabicDigits(trim($phone));
        $cleaned = (string) preg_replace('/[\s\-\(\)\.]/', '', $cleaned);

        if ($cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+'.substr($cleaned, 2);
        }

        $callingDigits = ltrim($callingCode, '+');

        if (str_starts_with($cleaned, $callingCode)) {
            $digits = substr($cleaned, strlen($callingCode));
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }

            return $callingCode.$digits;
        }

        if (str_starts_with($cleaned, $callingDigits) && strlen($cleaned) >= strlen($callingDigits) + 7) {
            $digits = substr($cleaned, strlen($callingDigits));
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }

            return $callingCode.$digits;
        }

        if ($callingCode === '+967') {
            if (str_starts_with($cleaned, '07') && strlen($cleaned) === 10) {
                return '+967'.substr($cleaned, 1);
            }

            if (str_starts_with($cleaned, '7') && strlen($cleaned) === 9) {
                return '+967'.$cleaned;
            }

            if (str_starts_with($cleaned, '0') && strlen($cleaned) >= 7 && strlen($cleaned) <= 9) {
                return '+967'.substr($cleaned, 1);
            }
        }

        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        if (preg_match('/^[1-9]\d{6,14}$/', $cleaned)) {
            return '+'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Check if the phone number is valid according to E.164 standard.
     */
    public static function isValid(?string $phone, ?int $countryId = null): bool
    {
        $callingCode = CountryCallingCode::forCountryId($countryId);
        $normalized = self::normalize($phone, $callingCode);
        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $normalized);
    }

    /**
     * Check if two phone numbers have the exact same canonical normalized form.
     */
    public static function areCanonicalEqual(?string $phone1, ?string $phone2, ?int $countryId = null): bool
    {
        $callingCode = CountryCallingCode::forCountryId($countryId);
        $norm1 = self::normalize($phone1, $callingCode);
        $norm2 = self::normalize($phone2, $callingCode);

        if ($norm1 === null || $norm2 === null) {
            return $norm1 === $norm2;
        }

        return $norm1 === $norm2;
    }

    private static function normalizeCallingCode(string $callingCode): string
    {
        $trimmed = trim($callingCode);

        return str_starts_with($trimmed, '+') ? $trimmed : '+'.$trimmed;
    }
}
