<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * Immutable [startAt, endAt) boundary in the project business timezone (Asia/Aden).
 *
 * startAt is inclusive, endAt is exclusive. Callers must filter with
 * `>= startAt` and `< endAt`, never `<=`, to avoid double-counting the
 * boundary instant.
 */
final readonly class ReportDateRangeData
{
    public const TIMEZONE = 'Asia/Aden';

    public const MAX_CUSTOM_RANGE_DAYS = 366;

    public function __construct(
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
    ) {}

    public static function fromPreset(string $preset, ?string $from = null, ?string $to = null): self
    {
        $now = CarbonImmutable::now(self::TIMEZONE);

        return match ($preset) {
            'today' => new self($now->startOfDay(), $now->startOfDay()->addDay()),
            'yesterday' => new self($now->startOfDay()->subDay(), $now->startOfDay()),
            'this_week' => new self($now->startOfWeek(), $now->startOfWeek()->addWeek()),
            'this_month' => new self($now->startOfMonth(), $now->startOfMonth()->addMonthNoOverflow()),
            'custom' => self::fromCustomRange($from, $to),
            default => throw new DomainException(sprintf('نطاق تاريخي غير معروف (%s).', $preset)),
        };
    }

    private static function fromCustomRange(?string $from, ?string $to): self
    {
        if ($from === null || $from === '' || $to === null || $to === '') {
            throw new DomainException('يجب تحديد تاريخ البداية والنهاية عند اختيار نطاق مخصص.');
        }

        $startAt = CarbonImmutable::parse($from, self::TIMEZONE)->startOfDay();
        $endAt = CarbonImmutable::parse($to, self::TIMEZONE)->startOfDay()->addDay();

        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw new DomainException('تاريخ النهاية يجب أن يكون بعد تاريخ البداية.');
        }

        if ($startAt->diffInDays($endAt) > self::MAX_CUSTOM_RANGE_DAYS) {
            throw new DomainException(sprintf('النطاق الزمني المخصص يتجاوز الحد الأقصى المسموح به (%d يوم).', self::MAX_CUSTOM_RANGE_DAYS));
        }

        return new self($startAt, $endAt);
    }
}
