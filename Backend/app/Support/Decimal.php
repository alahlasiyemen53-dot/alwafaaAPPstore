<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Decimal
{
    public const SCALE = 4;

    public static function normalize(string|int|float $value): string
    {
        if (is_float($value)) {
            $formatted = number_format($value, self::SCALE, '.', '');

            return $formatted;
        }

        $str = trim((string) $value);
        if ($str === '') {
            $str = '0';
        }

        // bcadd with 0 at SCALE formats cleanly
        return bcadd($str, '0', self::SCALE);
    }

    public static function add(string|int|float $left, string|int|float $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function sub(string|int|float $left, string|int|float $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function mul(string|int|float $left, string|int|float $right): string
    {
        return bcmul(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function div(string|int|float $left, string|int|float $right): string
    {
        $rightNorm = self::normalize($right);
        if (bccomp($rightNorm, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero in Decimal::div.');
        }

        return bcdiv(self::normalize($left), $rightNorm, self::SCALE);
    }

    public static function compare(string|int|float $left, string|int|float $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::SCALE);
    }

    public static function min(string|int|float $left, string|int|float $right): string
    {
        return self::compare($left, $right) <= 0 ? self::normalize($left) : self::normalize($right);
    }

    public static function max(string|int|float $left, string|int|float $right): string
    {
        return self::compare($left, $right) >= 0 ? self::normalize($left) : self::normalize($right);
    }

    public static function format(string|int|float $value, int $decimals = 2): string
    {
        $normalized = self::normalize($value);

        return bcadd($normalized, '0', $decimals);
    }
}
