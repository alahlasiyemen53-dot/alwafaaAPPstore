<?php

namespace Tests\Unit;

use App\DTOs\ReportDateRangeData;
use Carbon\CarbonImmutable;
use DomainException;
use Tests\TestCase;

class ReportDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_today_uses_aden_inclusive_start_and_exclusive_end(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-15 12:00:00', 'Asia/Aden')
        );

        $range = ReportDateRangeData::fromPreset('today');

        $this->assertSame('2026-09-15 00:00:00', $range->startAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-16 00:00:00', $range->endAt->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Aden', $range->startAt->getTimezone()->getName());
    }

    public function test_yesterday_is_the_day_before_today(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-15 12:00:00', 'Asia/Aden')
        );

        $range = ReportDateRangeData::fromPreset('yesterday');

        $this->assertSame('2026-09-14 00:00:00', $range->startAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 00:00:00', $range->endAt->format('Y-m-d H:i:s'));
    }

    public function test_this_week_starts_on_the_configured_week_start(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-15 12:00:00', 'Asia/Aden')
        );

        $range = ReportDateRangeData::fromPreset('this_week');

        $this->assertTrue($range->startAt->lessThanOrEqualTo(CarbonImmutable::parse('2026-09-15', 'Asia/Aden')));
        $this->assertSame(7.0, $range->startAt->diffInDays($range->endAt));
    }

    public function test_this_month_spans_the_full_calendar_month(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-15 12:00:00', 'Asia/Aden')
        );

        $range = ReportDateRangeData::fromPreset('this_month');

        $this->assertSame('2026-09-01 00:00:00', $range->startAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 00:00:00', $range->endAt->format('Y-m-d H:i:s'));
    }

    public function test_custom_range_is_inclusive_start_exclusive_end(): void
    {
        $range = ReportDateRangeData::fromPreset('custom', '2026-09-01', '2026-09-10');

        $this->assertSame('2026-09-01 00:00:00', $range->startAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 00:00:00', $range->endAt->format('Y-m-d H:i:s'));
    }

    public function test_custom_range_rejects_end_before_start(): void
    {
        $this->expectException(DomainException::class);

        ReportDateRangeData::fromPreset('custom', '2026-09-10', '2026-09-01');
    }

    public function test_custom_range_rejects_excessive_span(): void
    {
        $this->expectException(DomainException::class);

        ReportDateRangeData::fromPreset('custom', '2020-01-01', '2026-01-01');
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $this->expectException(DomainException::class);

        ReportDateRangeData::fromPreset('custom', null, null);
    }

    public function test_unknown_preset_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        ReportDateRangeData::fromPreset('not-a-real-preset');
    }
}
