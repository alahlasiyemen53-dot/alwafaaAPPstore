<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Str;

final class SkuGenerator
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Generate a unique, bounded, alphanumeric SKU.
     * Format: SKU-XXXXXXXX (where X is uppercase alphanumeric, total length 12 chars).
     */
    public function generate(?string $prefix = 'SKU'): string
    {
        $cleanPrefix = strtoupper(trim((string) $prefix));
        if ($cleanPrefix === '') {
            $cleanPrefix = 'SKU';
        }

        // Bounded prefix length to ensure overall length stays well within 64 chars
        $cleanPrefix = substr(preg_replace('/[^A-Z0-9]/', '', $cleanPrefix) ?: 'SKU', 0, 10);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            // Generate 8 characters of random alphanumeric string
            $randomPart = strtoupper(Str::random(8));
            $candidate = sprintf('%s-%s', $cleanPrefix, $randomPart);

            if (! ProductVariant::query()->where('sku', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fallback using microsecond timestamp + random chars if retry exhausted
        $fallback = sprintf('%s-%X%s', $cleanPrefix, (int) (microtime(true) * 1000), strtoupper(Str::random(4)));

        return substr($fallback, 0, 64);
    }
}
