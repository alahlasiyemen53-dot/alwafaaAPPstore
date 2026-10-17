<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\OrderItemData;
use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Warehouse;
use App\Services\DiscountAllocationService;
use App\Services\OrderPricingService;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketingBaseCurrencyPricingTest extends TestCase
{
    use RefreshDatabase;

    private Currency $baseCurrency;

    private Currency $usdCurrency;

    private Customer $customer;

    private Warehouse $warehouse;

    private OrderPricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->pricingService = app(OrderPricingService::class);

        // Base Currency: YER
        $this->baseCurrency = Currency::query()->create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => '﷼',
            'decimal_places' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Order Currency: USD
        $this->usdCurrency = Currency::query()->create([
            'code' => 'USD',
            'name_ar' => 'دولار أمريكي',
            'name_en' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => false,
            'is_active' => true,
        ]);

        // Direct rate: 1 YER = 0.004 USD
        ExchangeRate::query()->create([
            'from_currency_id' => $this->baseCurrency->id,
            'to_currency_id' => $this->usdCurrency->id,
            'rate' => '0.00400000',
            'effective_at' => now()->subDay(),
        ]);

        $this->customer = Customer::query()->create([
            'name' => 'عميل تجريبي',
            'phone' => '777000111',
            'normalized_phone' => '+967777000111',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'name_ar' => 'المستودع الرئيسي',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);
    }

    private function createVariantWithBasePrice(string $sku, string $basePrice): ProductVariant
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'default-cat'],
            ['name_ar' => 'تصنيف عام', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج '.$sku,
            'slug' => 'prod-'.strtolower($sku),
            'status' => 'active',
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'status' => 'active',
            'is_default' => true,
        ]);

        $variant->prices()->create([
            'currency_id' => $this->baseCurrency->id,
            'price' => $basePrice,
        ]);

        return $variant;
    }

    /**
     * Test 1: Base price 10000 YER, rate 0.004 USD, fixed Promotion price 7500 YER
     * => Order unit price 40 USD, discount 10 USD, net 30 USD
     */
    public function test_fixed_promotion_price_converts_from_base_currency_to_order_currency(): void
    {
        $variant = $this->createVariantWithBasePrice('VAR-PROMO-1', '10000.0000');

        // Fixed Promotion price: 7500 YER
        Promotion::query()->create([
            'code' => 'PROMO-7500',
            'name' => 'سعر ترويجي 7500 ريال',
            'type' => PromotionType::FixedPrice,
            'scope' => PromotionScope::All,
            'value' => 7500.0000,
            'is_active' => true,
            'priority' => 10,
        ]);

        $result = $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: '1.0000',
                ),
            ],
            customerId: $this->customer->id,
        );

        $this->assertSame('40.0000', number_format((float) $result->originalSubtotal, 4, '.', ''));
        $this->assertSame('10.0000', number_format((float) $result->promotionDiscountTotal, 4, '.', ''));
        $this->assertSame('30.0000', number_format((float) $result->finalTotal, 4, '.', ''));

        $item = $result->items[0];
        $this->assertSame('40.0000', number_format((float) $item['unit_price'], 4, '.', ''));
        $this->assertSame('10.0000', number_format((float) $item['discount_amount'], 4, '.', ''));
        $this->assertSame('30.0000', number_format((float) $item['line_total'], 4, '.', ''));
    }

    /**
     * Test 2: Fixed Coupon 1000 YER => 4 USD discount
     */
    public function test_fixed_coupon_converts_from_base_currency_to_order_currency(): void
    {
        $variant = $this->createVariantWithBasePrice('VAR-COUPON-1', '10000.0000');

        // Fixed Coupon: 1000 YER
        Coupon::query()->create([
            'code' => 'FIXED1000',
            'name' => 'خصم 1000 ريال',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 1000.0000,
            'is_active' => true,
        ]);

        $result = $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: '1.0000',
                ),
            ],
            couponCode: 'FIXED1000',
            customerId: $this->customer->id,
        );

        $this->assertSame('40.0000', number_format((float) $result->originalSubtotal, 4, '.', ''));
        $this->assertSame('4.0000', number_format((float) $result->couponDiscountTotal, 4, '.', ''));
        $this->assertSame('36.0000', number_format((float) $result->finalTotal, 4, '.', ''));
    }

    /**
     * Test 3: Coupon minimum 5000 YER => eligibility threshold 20 USD
     */
    public function test_coupon_minimum_order_amount_converts_from_base_currency(): void
    {
        // Variant base price: 3000 YER => 12 USD in USD currency
        $variantSmall = $this->createVariantWithBasePrice('VAR-SMALL', '3000.0000');
        // Variant base price: 10000 YER => 40 USD in USD currency
        $variantLarge = $this->createVariantWithBasePrice('VAR-LARGE', '10000.0000');

        Coupon::query()->create([
            'code' => 'MIN5000',
            'name' => 'حد أدنى 5000 ريال',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 500.0000,
            'minimum_order_amount' => 5000.0000, // 5000 YER => 20 USD
            'is_active' => true,
        ]);

        // Order subtotal is 12 USD (< 20 USD threshold) -> throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('الحد الأدنى للطلب');

        $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variantSmall->id,
                    quantity: '1.0000',
                ),
            ],
            couponCode: 'MIN5000',
            customerId: $this->customer->id,
        );
    }

    public function test_coupon_minimum_order_amount_succeeds_when_above_threshold(): void
    {
        $variantLarge = $this->createVariantWithBasePrice('VAR-LARGE-2', '10000.0000');

        Coupon::query()->create([
            'code' => 'MIN5000OK',
            'name' => 'حد أدنى 5000 ريال مقبول',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 500.0000, // 500 YER => 2 USD
            'minimum_order_amount' => 5000.0000, // 5000 YER => 20 USD
            'is_active' => true,
        ]);

        // 40 USD >= 20 USD threshold
        $result = $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variantLarge->id,
                    quantity: '1.0000',
                ),
            ],
            couponCode: 'MIN5000OK',
            customerId: $this->customer->id,
        );

        $this->assertSame('40.0000', number_format((float) $result->originalSubtotal, 4, '.', ''));
        $this->assertSame('2.0000', number_format((float) $result->couponDiscountTotal, 4, '.', ''));
        $this->assertSame('38.0000', number_format((float) $result->finalTotal, 4, '.', ''));
    }

    /**
     * Test 4: Fixed Bundle price 15000 YER => 60 USD bundle price
     */
    public function test_fixed_bundle_price_converts_from_base_currency_to_order_currency(): void
    {
        $variantA = $this->createVariantWithBasePrice('BND-VAR-A', '10000.0000'); // 40 USD
        $variantB = $this->createVariantWithBasePrice('BND-VAR-B', '10000.0000'); // 40 USD

        $bundle = Bundle::query()->create([
            'code' => 'COMBO-15K',
            'name' => 'باقة كومبو 15000 ريال',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 15000.0000, // 15000 YER => 60 USD
            'is_active' => true,
            'priority' => 10,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $variantA->id,
            'quantity' => '1.0000',
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_variant_id' => $variantB->id,
            'quantity' => '1.0000',
        ]);

        $result = $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variantA->id,
                    quantity: '1.0000',
                ),
                new OrderItemData(
                    productVariantId: $variantB->id,
                    quantity: '1.0000',
                ),
            ],
            customerId: $this->customer->id,
        );

        // Original subtotal = 40 + 40 = 80 USD
        $this->assertSame('80.0000', number_format((float) $result->originalSubtotal, 4, '.', ''));
        // Bundle price is 60 USD, so bundle discount = 80 - 60 = 20 USD
        $this->assertSame('20.0000', number_format((float) $result->bundleDiscountTotal, 4, '.', ''));
        $this->assertSame('60.0000', number_format((float) $result->finalTotal, 4, '.', ''));
    }

    /**
     * Test 5: Percentage discounts produce the same economic result in Base and Order currencies
     */
    public function test_percentage_discounts_produce_identical_economic_results(): void
    {
        $variant = $this->createVariantWithBasePrice('VAR-PCT', '10000.0000');

        Promotion::query()->create([
            'code' => 'PCT-20',
            'name' => 'خصم 20%',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::All,
            'value' => 20.0000,
            'is_active' => true,
            'priority' => 10,
        ]);

        // Base currency (YER)
        $resultBase = $this->pricingService->calculate(
            currencyId: $this->baseCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: '1.0000',
                ),
            ],
            customerId: $this->customer->id,
        );

        // Order currency (USD)
        $resultUsd = $this->pricingService->calculate(
            currencyId: $this->usdCurrency->id,
            itemsData: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: '1.0000',
                ),
            ],
            customerId: $this->customer->id,
        );

        $this->assertSame('10000.0000', number_format((float) $resultBase->originalSubtotal, 4, '.', ''));
        $this->assertSame('2000.0000', number_format((float) $resultBase->promotionDiscountTotal, 4, '.', ''));
        $this->assertSame('8000.0000', number_format((float) $resultBase->finalTotal, 4, '.', ''));

        $this->assertSame('40.0000', number_format((float) $resultUsd->originalSubtotal, 4, '.', ''));
        $this->assertSame('8.0000', number_format((float) $resultUsd->promotionDiscountTotal, 4, '.', ''));
        $this->assertSame('32.0000', number_format((float) $resultUsd->finalTotal, 4, '.', ''));

        // Economic equivalence: 8000 YER * 0.004 = 32.0000 USD
        $this->assertSame('32.0000', number_format((float) $resultBase->finalTotal * 0.004, 4, '.', ''));
    }

    /**
     * Test 6: Three-line proportional allocation assigns the rounding residual deterministically to the final eligible line
     */
    public function test_three_line_proportional_allocation_assigns_residual_to_final_eligible_line(): void
    {
        $allocationService = app(DiscountAllocationService::class);

        // Three equal lines of 10.0000
        $lines = [
            'line_1' => '10.0000',
            'line_2' => '10.0000',
            'line_3' => '10.0000',
        ];

        // Total discount of 10.0000 across 30.0000 (1/3 each)
        // 10 / 3 = 3.333333...
        // Line 1: 3.3333
        // Line 2: 3.3333
        // Line 3: 3.3334 (receives 0.0001 residual)
        $allocated = $allocationService->allocate($lines, '10.0000');

        $this->assertSame('3.3333', number_format($allocated['line_1'], 4, '.', ''));
        $this->assertSame('3.3333', number_format($allocated['line_2'], 4, '.', ''));
        $this->assertSame('3.3334', number_format($allocated['line_3'], 4, '.', ''));

        $sum = array_sum($allocated);
        $this->assertSame('10.0000', number_format($sum, 4, '.', ''));
    }
}
