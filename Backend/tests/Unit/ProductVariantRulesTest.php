<?php

namespace Tests\Unit;

use App\Enums\ProductVariantStatus;
use App\Rules\VariantPriceRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductVariantRulesTest extends TestCase
{
    #[DataProvider('priceCases')]
    public function test_variant_prices_require_nonnegative_price_and_higher_compare_at_price(
        int|float $price,
        int|float|null $compareAtPrice,
        bool $valid,
    ): void {
        self::assertSame($valid, VariantPriceRule::passes($price, $compareAtPrice));
    }

    public static function priceCases(): array
    {
        return [
            'zero without comparison' => [0, null, true],
            'positive without comparison' => [10.25, null, true],
            'comparison above price' => [10, 12, true],
            'negative price' => [-1, null, false],
            'negative comparison' => [0, -1, false],
            'comparison equal to price' => [10, 10, false],
            'comparison below price' => [10, 9, false],
        ];
    }

    public function test_variant_status_contains_only_catalog_lifecycle_states(): void
    {
        self::assertSame(['active', 'inactive'], array_column(ProductVariantStatus::cases(), 'value'));
    }
}
