<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class DateFilterNormalizer
{
    /**
     * Normalize incoming date strings (d-m-Y, d/m/Y, Y-m-d) to standard Y-m-d for database operations.
     */
    public static function normalize(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $trimmed = trim($date);

        try {
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $trimmed)) {
                return Carbon::createFromFormat('d-m-Y', $trimmed)->format('Y-m-d');
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
                return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            }

            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format a date string or instance into standard Day-Month-Year (d-m-Y) for user interface inputs and displays.
     */
    public static function formatDisplay(mixed $date): string
    {
        if ($date === null || (is_string($date) && trim($date) === '')) {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('d-m-Y');
        }

        $normalized = self::normalize((string) $date);
        if ($normalized === null) {
            return (string) $date;
        }

        try {
            return Carbon::parse($normalized)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }
}
