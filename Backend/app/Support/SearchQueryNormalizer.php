<?php

declare(strict_types=1);

namespace App\Support;

final class SearchQueryNormalizer
{
    public static function normalize(string $query): string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        if ($trimmed === '') {
            return '';
        }

        // Light Arabic/Latin case fold without inventing a separate search engine normalizer.
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($trimmed, 'UTF-8');
        }

        return strtolower($trimmed);
    }
}
