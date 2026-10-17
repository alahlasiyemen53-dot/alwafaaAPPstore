<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerCurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_currencies_with_a_canonical_rate(): void
    {
        $yer = Currency::query()->create([
            'code' => 'YER', 'name_ar' => 'ريال يمني', 'symbol' => '﷼',
            'decimal_places' => 0, 'is_active' => true,
        ]);
        $sar = Currency::query()->create([
            'code' => 'SAR', 'name_ar' => 'ريال سعودي', 'symbol' => 'ر.س',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $inactive = Currency::query()->create([
            'code' => 'USD', 'name_ar' => 'دولار', 'symbol' => '$',
            'decimal_places' => 2, 'is_active' => false,
        ]);
        Setting::query()->create([
            'key' => 'default_currency_id', 'value' => (string) $yer->id, 'type' => 'integer',
        ]);
        ExchangeRate::query()->create([
            'from_currency_id' => $sar->id,
            'to_currency_id' => $yer->id,
            'rate' => '140.00000000',
            'effective_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/v1/currencies')->assertOk();

        $response->assertJsonPath('data.0.code', 'YER')
            ->assertJsonPath('data.0.is_base', true)
            ->assertJsonPath('data.0.rate', '1.00000000')
            ->assertJsonPath('data.1.code', 'SAR')
            ->assertJsonPath('data.1.is_base', false)
            ->assertJsonPath('data.1.rate', '140.00000000');
        $response->assertJsonMissing(['id' => $inactive->id]);
    }
}
