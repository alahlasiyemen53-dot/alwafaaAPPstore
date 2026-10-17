<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\Order;
use App\Models\Setting;
use App\Services\LoyaltyBalanceQuery;
use App\Services\LoyaltyCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoyaltyCalculationTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyCalculationService $calculationService;

    private LoyaltyBalanceQuery $balanceQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculationService = new LoyaltyCalculationService;
        $this->balanceQuery = new LoyaltyBalanceQuery;
    }

    public function test_calculates_points_from_historical_base_currency_order_amount(): void
    {
        // Default policy: 1 point per 1000 base currency
        Setting::updateOrCreate(['key' => 'loyalty.spend_per_point'], ['value' => '1000', 'type' => 'decimal']);

        $customer = Customer::create(['name' => 'عميل تجريبي', 'phone' => '770000001', 'normalized_phone' => '770000001']);
        $currency = Currency::create(['name_ar' => 'ريال يمني', 'name_en' => 'YER', 'code' => 'YER', 'symbol' => 'ر.ي', 'is_base' => true, 'is_active' => true]);

        $order = new Order([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'subtotal' => 25000.0,
            'discount_amount' => 5000.0,
            'total' => 20000.0,
            'base_currency_total' => '20000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $points = $this->calculationService->calculateOrderPoints($order);
        self::assertSame(20, $points);
    }

    public function test_respects_minimum_order_amount_and_max_points_cap(): void
    {
        Setting::updateOrCreate(['key' => 'loyalty.spend_per_point'], ['value' => '1000', 'type' => 'decimal']);
        Setting::updateOrCreate(['key' => 'loyalty.min_order_amount'], ['value' => '5000', 'type' => 'decimal']);
        Setting::updateOrCreate(['key' => 'loyalty.max_points_per_order'], ['value' => '15', 'type' => 'integer']);

        $customer = Customer::create(['name' => 'عميل تجريبي', 'phone' => '770000002', 'normalized_phone' => '770000002']);
        $currency = Currency::create(['name_ar' => 'ريال يمني', 'name_en' => 'YER', 'code' => 'YER', 'symbol' => 'ر.ي', 'is_base' => true, 'is_active' => true]);

        // Below minimum threshold: 4000 < 5000 -> 0 points
        $orderSmall = new Order([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'total' => 4000.0,
            'base_currency_total' => '4000.0000',
        ]);
        self::assertSame(0, $this->calculationService->calculateOrderPoints($orderSmall));

        // Exceeds max points cap: 50,000 would be 50 points, capped at 15
        $orderLarge = new Order([
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'total' => 50000.0,
            'base_currency_total' => '50000.0000',
        ]);
        self::assertSame(15, $this->calculationService->calculateOrderPoints($orderLarge));
    }

    public function test_balance_query_derives_correct_available_and_lifetime_points(): void
    {
        $customer = Customer::create(['name' => 'عميل تجريبي', 'phone' => '770000003', 'normalized_phone' => '770000003']);
        $account = CustomerLoyaltyAccount::create(['customer_id' => $customer->id]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $account->id,
            'entry_number' => 'LOY-001',
            'type' => 'earn',
            'points' => 100,
            'source_type' => 'Order',
            'source_id' => 1,
            'source_event' => 'DELIVERY_DELIVERED',
            'occurred_at' => now(),
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $account->id,
            'entry_number' => 'LOY-002',
            'type' => 'redeem',
            'points' => 30,
            'source_type' => 'Order',
            'source_id' => 2,
            'source_event' => 'ORDER_REDEEM',
            'occurred_at' => now(),
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $account->id,
            'entry_number' => 'LOY-003',
            'type' => 'adjustment_credit',
            'points' => 20,
            'source_type' => 'ManualAdjustment',
            'source_id' => 1,
            'source_event' => 'ADMIN_ADJUSTMENT',
            'occurred_at' => now(),
        ]);

        $balance = $this->balanceQuery->calculate($account);

        self::assertSame(90, $balance['available_points']); // 100 - 30 + 20
        self::assertSame(100, $balance['lifetime_earned']);
        self::assertSame(30, $balance['lifetime_redeemed']);
        self::assertSame(0, $balance['active_reserved_points']);
    }
}
