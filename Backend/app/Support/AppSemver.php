<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses and compares Flutter-style versions: MAJOR.MINOR.PATCH[+BUILD].
 */
final class AppSemver
{
    /**
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    public static function parse(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || ! preg_match('/^(\d+)\.(\d+)\.(\d+)(?:\+(\d+))?$/', $trimmed, $m)) {
            return null;
        }

        return [
            (int) $m[1],
            (int) $m[2],
            (int) $m[3],
            isset($m[4]) ? (int) $m[4] : 0,
        ];
    }

    public static function isValid(?string $raw): bool
    {
        return self::parse($raw) !== null;
    }

    /** @return int negative if $a < $b, 0 if equal, positive if $a > $b */
    public static function compare(string $a, string $b): int
    {
        $left = self::parse($a);
        $right = self::parse($b);
        if ($left === null || $right === null) {
            return 0;
        }

        foreach ([0, 1, 2, 3] as $i) {
            if ($left[$i] !== $right[$i]) {
                return $left[$i] <=> $right[$i];
            }
        }

        return 0;
    }

    public static function max(?string ...$versions): ?string
    {
        $best = null;
        foreach ($versions as $version) {
            if (! self::isValid($version)) {
                continue;
            }
            if ($best === null || self::compare((string) $version, $best) > 0) {
                $best = trim((string) $version);
            }
        }

        return $best;
    }
}
