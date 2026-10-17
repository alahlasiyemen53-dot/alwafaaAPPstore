<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\CreateSalesReturnAction;
use App\Actions\PostSalesReturnAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\DTOs\SalesReturnData;
use App\DTOs\SalesReturnItemData;
use App\Enums\CouponDiscountType;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesReturnPricingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'ماجد علي',
            'phone' => '775566778',
            'normalized_phone' => '+967775566778',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'ماجد علي',
            'contact_phone' => '775566778',
            'address_line' => 'صنعاء - الستين',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'ملابس',
            'slug' => 'clothing',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة ملابس', 'slug' => 'clothing-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name_ar' => 'قميص قطني',
            'name_en' => 'Cotton Shirt',
            'slug' => 'shirt-1',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHIRT-M',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 100.00,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 50,
        ]);
    }

    public function test_partial_sales_return_uses_historical_net_discounted_value(): void
    {
        // 1. Coupon for 40 YER discount
        Coupon::create([
            'code' => 'MINUS40',
            'name' => 'خصم 40 ريال',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 40.00,
            'is_active' => true,
        ]);

        // 2. Buy 2 items @ 100 YER = 200 YER gross.
        // Coupon discount = 40 YER => Net total = 160 YER (80 YER net per unit).
        $orderDto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            notes: 'طلب خصم للمرتجع',
            items: [
                new OrderItemData(productVariantId: $this->variant->id, quantity: 2),
            ],
            couponCode: 'MINUS40',
        );

        $order = app(CreateOrderAction::class)->execute($orderDto, $this->admin->id);
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        $this->assertEquals(160.00, (float) $confirmedOrder->total);
        $orderItem = $confirmedOrder->items->first();
        $this->assertEquals(160.00, (float) $orderItem->line_total);

        // 3. Simulate physical sale out movement
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::Sale,
            'direction' => StockMovementDirection::Out,
            'quantity' => 2.0,
            'reference_type' => 'Order',
            'reference_id' => $confirmedOrder->id,
            'occurred_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        // 4. Return 1 unit
        $createReturnAction = app(CreateSalesReturnAction::class);
        $postReturnAction = app(PostSalesReturnAction::class);

        $returnData = new SalesReturnData(
            orderId: $confirmedOrder->id,
            warehouseId: $this->warehouse->id,
            reasonCode: 'customer_changed_mind',
            notes: 'إرجاع قميص واحد',
            items: [
                new SalesReturnItemData(
                    orderItemId: $orderItem->id,
                    productVariantId: $this->variant->id,
                    returnQuantity: 1.0,
                ),
            ]
        );

        $salesReturn = $createReturnAction->execute($returnData, $this->admin->id);

        // The refund total for 1 unit should be 80.00 YER (net), NOT 100.00 YER (gross)
        $salesReturnItem = SalesReturnItem::query()
            ->where('sales_return_id', $salesReturn->id)
            ->firstOrFail();

        $this->assertEquals(80.00, (float) $salesReturn->total);
        $this->assertEquals(80.00, (float) $salesReturnItem->unit_price_snapshot);

        // 5. Post return
        $postedReturn = $postReturnAction->execute($salesReturn, $this->admin->id);
        $this->assertSame(SalesReturnStatus::Posted, $postedReturn->status);
        $this->assertEquals(80.00, (float) $postedReturn->total);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
