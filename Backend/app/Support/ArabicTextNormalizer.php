<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalizes Arabic text for tolerant product search
 * (alef/hamza variants, teh marbuta, alef maqsura, tatweel, diacritics).
 */
final class ArabicTextNormalizer
{
    public static function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = mb_strtolower(trim($value), 'UTF-8');

        // Remove tatweel / kashida
        $text = str_replace('ـ', '', $text);

        // Strip Arabic diacritics (tashkeel)
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;

        // Alef variants → ا
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);

        // Teh marbuta → heh, alef maqsura → yeh
        $text = str_replace(['ة', 'ى'], ['ه', 'ي'], $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Build a MySQL REPLACE chain that mirrors normalize() for a column.
     */
    public static function sqlNormalizeExpression(string $column): string
    {
        $expr = $column;
        $expr = "REPLACE({$expr}, 'ـ', '')";
        foreach (['أ', 'إ', 'آ', 'ٱ'] as $alef) {
            $expr = "REPLACE({$expr}, '{$alef}', 'ا')";
        }
        $expr = "REPLACE({$expr}, 'ة', 'ه')";
        $expr = "REPLACE({$expr}, 'ى', 'ي')";

        return $expr;
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
