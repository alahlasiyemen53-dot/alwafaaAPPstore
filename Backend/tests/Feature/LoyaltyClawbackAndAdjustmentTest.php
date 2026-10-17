<?php

namespace Tests\Feature;

use App\Actions\AdjustLoyaltyPointsAction;
use App\Actions\ConfirmCustomerDeliveryAction;
use App\Actions\MarkDeliveryDeliveredAction;
use App\Actions\PostSalesReturnAction;
use App\DTOs\LoyaltyAdjustmentData;
use App\Enums\DeliveryStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LoyaltyBalanceQuery;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoyaltyClawbackAndAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Currency $currency;

    private Warehouse $warehouse;

    private Driver $driver;

    private ProductVariant $variant;

    private LoyaltyBalanceQuery $balanceQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مسؤول النظام',
            'email' => 'admin@lamma.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'سعيد باوزير',
            'phone' => '772233445',
            'normalized_phone' => '772233445',
            'is_active' => true,
        ]);

        $this->currency = Currency::create([
            'name_ar' => 'ريال يمني',
            'name_en' => 'YER',
            'code' => 'YER',
            'symbol' => 'ر.ي',
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع صنعاء',
            'name_en' => 'Sanaa Warehouse',
            'code' => 'WH-SAN',
            'is_active' => true,
        ]);

        $this->driver = Driver::create([
            'name' => 'سائق التوصيل',
            'code' => 'DRV-002',
            'phone' => '771122334',
            'is_active' => true,
        ]);

        $unit = Unit::create(['code' => 'PCS', 'name_ar' => 'حبة', 'decimal_places' => 0, 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة أصلية', 'slug' => 'brand-orig', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'إلكترونيات', 'slug' => 'electronics', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'هاتف ذكي',
            'slug' => 'smartphone-1',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PHONE-SM-01',
            'barcode' => '998877665544',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->balanceQuery = new LoyaltyBalanceQuery;
        Setting::updateOrCreate(['key' => 'loyalty.spend_per_point'], ['value' => '1000', 'type' => 'decimal']);
    }

    public function test_posting_sales_return_claws_back_proportional_points(): void
    {
        // 1. Create and deliver Order with 2 items worth 20,000 (earns 20 points)
        $order = Order::create([
            'order_number' => 'ORD-2001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'status' => OrderStatus::AssignedToDriver,
            'total' => 20000.0,
            'base_currency_total' => '20000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'هاتف ذكي',
            'sku_snapshot' => 'PHONE-SM-01',
            'quantity' => 2.0,
            'base_quantity' => 2.0,
            'unit_price' => 10000.0,
            'base_currency_unit_price' => '10000.0000',
            'line_subtotal' => 20000.0,
            'base_currency_line_subtotal' => '20000.0000',
            'discount_amount' => 0.0,
            'line_total' => 20000.0,
            'base_currency_line_total' => '20000.0000',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-2001',
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => now(),
            'cod_amount_to_collect' => 20000.0,
        ]);

        // Stock movement out for order
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::Sale,
            'direction' => StockMovementDirection::Out,
            'quantity' => 2.0,
            'reference_type' => 'Order',
            'reference_id' => $order->id,
            'occurred_at' => now(),
        ]);

        app(MarkDeliveryDeliveredAction::class)->execute($delivery, 20000.0, null, $this->admin->id);
        app(ConfirmCustomerDeliveryAction::class)->execute($order->fresh(), (int) $this->customer->id);

        $account = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(20, $this->balanceQuery->getAvailablePoints($account));

        // 2. Create and Post Sales Return for 1 item (10,000 YER, 50% of order -> claws back 10 points)
        $salesReturn = SalesReturn::create([
            'return_number' => 'RET-2001',
            'order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'reason_code' => ReturnReasonCode::DamagedItem,
            'status' => SalesReturnStatus::Draft,
            'total' => 10000.0,
            'base_currency_total' => '10000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        SalesReturnItem::create([
            'sales_return_id' => $salesReturn->id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'هاتف ذكي',
            'sku_snapshot' => 'PHONE-SM-01',
            'reason_code' => ReturnReasonCode::DamagedItem,
            'return_quantity' => 1.0,
            'return_base_quantity' => 1.0,
            'unit_price_snapshot' => 10000.0,
            'base_currency_unit_price_snapshot' => 10000.0,
            'line_total' => 10000.0,
            'base_currency_line_total' => '10000.0000',
        ]);

        app(PostSalesReturnAction::class)->execute($salesReturn, $this->admin->id);

        // Assert points clawed back
        self::assertSame(10, $this->balanceQuery->getAvailablePoints($account));

        $clawbackEntry = CustomerLoyaltyEntry::where('customer_loyalty_account_id', $account->id)
            ->where('type', LoyaltyEntryType::Clawback)
            ->first();

        self::assertNotNull($clawbackEntry);
        self::assertSame(10, $clawbackEntry->points);
        self::assertSame('SalesReturn', $clawbackEntry->source_type);
        self::assertSame($salesReturn->id, $clawbackEntry->source_id);
    }

    public function test_admin_manual_points_adjustment(): void
    {
        $adjustAction = app(AdjustLoyaltyPointsAction::class);

        // 1. Credit adjustment (+50 points)
        $creditData = new LoyaltyAdjustmentData(
            customerId: $this->customer->id,
            points: 50,
            type: 'credit',
            reason: 'مكافأة ولاء إدارية خاصة',
            createdBy: $this->admin->id,
        );
        $entry1 = $adjustAction->execute($creditData);

        self::assertSame(LoyaltyEntryType::AdjustmentCredit, $entry1->type);
        self::assertSame(50, $entry1->points);

        $account = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(50, $this->balanceQuery->getAvailablePoints($account));

        // 2. Debit adjustment (-20 points)
        $debitData = new LoyaltyAdjustmentData(
            customerId: $this->customer->id,
            points: 20,
            type: 'debit',
            reason: 'تسوية خصم نقاط',
            createdBy: $this->admin->id,
        );
        $entry2 = $adjustAction->execute($debitData);

        self::assertSame(LoyaltyEntryType::AdjustmentDebit, $entry2->type);
        self::assertSame(20, $entry2->points);
        self::assertSame(30, $this->balanceQuery->getAvailablePoints($account));

        // 3. Debit exceeding available points throws DomainException
        $excessData = new LoyaltyAdjustmentData(
            customerId: $this->customer->id,
            points: 100,
            type: 'debit',
            reason: 'خصم زائد غير مسموح',
            createdBy: $this->admin->id,
        );

        $this->expectException(DomainException::class);
        $adjustAction->execute($excessData);
    }
}
