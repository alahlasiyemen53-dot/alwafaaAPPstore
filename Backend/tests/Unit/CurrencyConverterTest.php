<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new CurrencyConverter;
    }

    public function test_mandatory_sprint_21_1_base_to_target_division(): void
    {
        $yer = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $sar = Currency::query()->create([
            'code' => 'SAR',
            'name_ar' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_default' => false,
            'is_active' => true,
        ]);

        Setting::query()->create([
            'key' => 'default_currency_id',
            'value' => (string) $yer->id,
            'type' => 'integer',
        ]);

        // Authoritative rate: 1 SAR = 140 YER
        // from = SAR, to = YER, rate = 140.00000000
        ExchangeRate::query()->create([
            'from_currency_id' => $sar->id,
            'to_currency_id' => $yer->id,
            'rate' => '140.00000000',
            'effective_at' => now()->subMinute(),
        ]);

        $this->converter->clearCache();

        // 10,000 YER / 140 = 71.43 SAR
        $result = $this->converter->convertFromBase('10000', $sar);

        self::assertTrue($result->isAvailable);
        self::assertFalse($result->isBase);
        self::assertSame('71.43', $result->amount);
        self::assertSame('71.43 ر.س', $result->formatted);

        // Mandatory negative assertion: MUST NOT be multiplication (1,400,000)
        self::assertNotSame('1400000.00', $result->amount);
        self::assertNotSame('1400000', $result->amount);

        // Target -> Base: 71.43 * 140 = 10000.20 YER
        $baseResult = $this->converter->convertToBase('71.43', $sar);
        self::assertTrue($baseResult->isAvailable);
        self::assertTrue($baseResult->isBase);
        self::assertSame('10000.20', $baseResult->amount);
    }

    public function test_same_currency_returns_amount_unchanged(): void
    {
        $yer = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        $result = $this->converter->convert('75000', $yer, $yer);

        self::assertTrue($result->isAvailable);
        self::assertTrue($result->isBase);
        self::assertSame('75000', $result->amount);
        self::assertSame('75,000 ﷼', $result->formatted);
        self::assertSame('1.00000000', $result->rateUsed);
    }

    public function test_setting_is_sole_authority_for_base_currency(): void
    {
        $yer = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 0,
            'is_default' => false,
            'is_active' => true,
        ]);

        $sar = Currency::query()->create([
            'code' => 'SAR',
            'name_ar' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_default' => true, // legacy flag intentionally on SAR
            'is_active' => true,
        ]);

        // Setting explicitly points to YER
        Setting::query()->create([
            'key' => 'default_currency_id',
            'value' => (string) $yer->id,
            'type' => 'integer',
        ]);

        $this->converter->clearCache();
        $base = $this->converter->getBaseCurrency();

        // Setting overrides is_default column!
        self::assertSame($yer->id, $base->id);
        self::assertSame('YER', $base->code);
    }

    public function test_missing_rate_returns_unavailable_without_fallback_to_one(): void
    {
        $yer = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        $eur = Currency::query()->create([
            'code' => 'EUR',
            'name_ar' => 'يورو',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_default' => false,
            'is_active' => true,
        ]);

        $result = $this->converter->convertBase('50000', $eur);

        self::assertFalse($result->isAvailable);
        self::assertNull($result->amount);
        self::assertSame('غير متاح', $result->formatted);
    }

    public function test_fails_when_no_active_base_currency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('لم يتم العثور على عملة أساسية نشطة للنظام.');

        $this->converter->getBaseCurrency();
    }
}
