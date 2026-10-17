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
use App\Enums\BundlePricingType;
use App\Enums\CouponDiscountType;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarketingSnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $baseCurrency;

    private ProductVariant $variantA;

    private ProductVariant $variantB;

    private Promotion $promotion;

    private Bundle $bundle;

    private Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->baseCurrency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'سالم عبد الله',
            'phone' => '771122334',
            'normalized_phone' => '+967771122334',
            'is_active' => true,
        ]);

        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المكتب',
            'contact_name' => 'سالم عبد الله',
            'contact_phone' => '771122334',
            'address_line' => 'صنعاء - حدة',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $cat = Category::create(['name_ar' => 'عام', 'slug' => 'general', 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'عام', 'slug' => 'general-brand', 'is_active' => true]);

        $productA = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج أ',
            'slug' => 'prod-a',
            'status' => 'active',
        ]);
        $this->variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'sku' => 'SKU-A',
            'status' => 'active',
            'is_default' => true,
        ]);
        VariantPrice::create([
            'product_variant_id' => $this->variantA->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 1000.00,
        ]);
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 100,
        ]);

        $productB = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'name_ar' => 'منتج ب',
            'slug' => 'prod-b',
            'status' => 'active',
        ]);
        $this->variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'sku' => 'SKU-B',
            'status' => 'active',
            'is_default' => true,
        ]);
        VariantPrice::create([
            'product_variant_id' => $this->variantB->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 2000.00,
        ]);
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 100,
        ]);

        // Promotion on variant A: 10% discount
        $this->promotion = Promotion::create([
            'name' => 'خصم 10% على منتج أ',
            'code' => 'PROMO10',
            'type' => PromotionType::Percentage,
            'value' => 10.00,
            'scope' => PromotionScope::Variant,
            'priority' => 1,
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $this->promotion->id,
            'target_type' => 'variant',
            'target_id' => $this->variantA->id,
        ]);

        // Bundle: 1 Variant A + 1 Variant B for fixed 2500 YER (discount = 500 YER)
        $this->bundle = Bundle::create([
            'name' => 'حزمة التوفير',
            'code' => 'BUNDLE1',
            'pricing_type' => BundlePricingType::FixedBundlePrice,
            'value' => 2500.00,
            'priority' => 10,
            'is_active' => true,
        ]);
        BundleItem::create([
            'bundle_id' => $this->bundle->id,
            'product_variant_id' => $this->variantA->id,
            'quantity' => 1,
        ]);
        BundleItem::create([
            'bundle_id' => $this->bundle->id,
            'product_variant_id' => $this->variantB->id,
            'quantity' => 1,
        ]);

        // Coupon: 200 YER fixed off
        $this->coupon = Coupon::create([
            'name' => 'كوبون ترحيبي',
            'code' => 'WELCOME200',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 200.00,
            'scope' => 'all',
            'is_compatible_with_promotions' => true,
            'is_active' => true,
        ]);
    }

    public function test_confirmed_order_snapshots_are_immutable_after_campaign_changes(): void
    {
        // Order: 2 Variant A + 1 Variant B
        // Bundle consumes 1 A + 1 B: original 3000 -> 2500 (bundle discount = 500)
        // Surplus 1 A gets Promotion (10% of 1000 = 100 discount)
        // Subtotal after item discounts = 2500 + 900 = 3400
        // Coupon discount = 200
        // Final Total = 3200
        $orderDto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->baseCurrency->id,
            notes: 'طلب فحص ثبات اللقطات التاريخية',
            items: [
                new OrderItemData(productVariantId: $this->variantA->id, quantity: 2),
                new OrderItemData(productVariantId: $this->variantB->id, quantity: 1),
            ],
            couponCode: 'WELCOME200',
        );

        $order = app(CreateOrderAction::class)->execute($orderDto, $this->admin->id);
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->admin->id);

        // Verify initial snapshot integrity on Order
        $this->assertSame('WELCOME200', $confirmedOrder->coupon_code_snapshot);
        $this->assertSame('كوبون ترحيبي', $confirmedOrder->coupon_name_snapshot);
        $this->assertSame(CouponDiscountType::FixedAmount->value, $confirmedOrder->coupon_discount_type_snapshot);
        $this->assertEquals(200.00, (float) $confirmedOrder->coupon_discount_value_base_snapshot);
        $this->assertEquals(500.00, (float) $confirmedOrder->bundle_discount_total);
        $this->assertEquals(100.00, (float) $confirmedOrder->promotion_discount_total);
        $this->assertEquals(200.00, (float) $confirmedOrder->coupon_discount_total);
        $this->assertEquals(800.00, (float) $confirmedOrder->discount_amount);
        $this->assertEquals(4000.00, (float) $confirmedOrder->subtotal);
        $this->assertEquals(3200.00, (float) $confirmedOrder->total);

        // Verify initial snapshot integrity on OrderItems
        $items = $confirmedOrder->items()->orderBy('id')->get();
        $itemA = $items->firstWhere('product_variant_id', $this->variantA->id);
        $itemB = $items->firstWhere('product_variant_id', $this->variantB->id);

        $this->assertNotNull($itemA);
        $this->assertSame('خصم 10% على منتج أ', $itemA->promotion_name_snapshot);
        $this->assertSame(PromotionType::Percentage->value, $itemA->promotion_type_snapshot);
        $this->assertEquals(10.00, (float) $itemA->promotion_value_base_snapshot);
        $this->assertSame('BUNDLE1', $itemA->bundle_code_snapshot);
        $this->assertSame('حزمة التوفير', $itemA->bundle_name_snapshot);
        $this->assertSame(BundlePricingType::FixedBundlePrice->value, $itemA->bundle_pricing_type_snapshot);
        $this->assertEquals(2500.00, (float) $itemA->bundle_value_base_snapshot);

        $this->assertNotNull($itemB);
        $this->assertSame('BUNDLE1', $itemB->bundle_code_snapshot);
        $this->assertSame('حزمة التوفير', $itemB->bundle_name_snapshot);
        $this->assertSame(BundlePricingType::FixedBundlePrice->value, $itemB->bundle_pricing_type_snapshot);
        $this->assertEquals(2500.00, (float) $itemB->bundle_value_base_snapshot);

        // Record confirmed snapshot values
        $confirmedOrderSnapshot = $confirmedOrder->only([
            'subtotal',
            'discount_amount',
            'promotion_discount_total',
            'bundle_discount_total',
            'coupon_discount_total',
            'total',
            'exchange_rate_snapshot',
            'base_currency_total',
            'coupon_code_snapshot',
            'coupon_name_snapshot',
            'coupon_discount_type_snapshot',
            'coupon_discount_value_base_snapshot',
        ]);

        $itemASnapshots = $itemA->only([
            'unit_price',
            'line_subtotal',
            'discount_amount',
            'item_discount_amount',
            'allocated_order_discount',
            'line_total',
            'promotion_name_snapshot',
            'promotion_type_snapshot',
            'promotion_value_base_snapshot',
            'bundle_code_snapshot',
            'bundle_name_snapshot',
            'bundle_pricing_type_snapshot',
            'bundle_value_base_snapshot',
        ]);

        // NOW: Drastically mutate / deactivate marketing campaigns and prices
        $this->promotion->update(['is_active' => false, 'value' => 50.00, 'name' => 'عرض ملغى']);
        $this->bundle->update(['is_active' => false, 'value' => 1000.00, 'name' => 'حزمة ملغاة']);
        $this->coupon->update(['is_active' => false, 'discount_value' => 999.00, 'name' => 'كوبون ملغى']);
        VariantPrice::where('product_variant_id', $this->variantA->id)->update(['price' => 5000.00]);
        VariantPrice::where('product_variant_id', $this->variantB->id)->update(['price' => 8000.00]);

        // Reload Order and Items from database
        $reloadedOrder = Order::query()->with('items')->findOrFail($confirmedOrder->id);
        $reloadedItemA = $reloadedOrder->items->firstWhere('product_variant_id', $this->variantA->id);
        $reloadedItemB = $reloadedOrder->items->firstWhere('product_variant_id', $this->variantB->id);

        // Assert all order-level snapshot attributes remain identical
        foreach ($confirmedOrderSnapshot as $attr => $expectedVal) {
            $this->assertEquals($expectedVal, $reloadedOrder->{$attr}, "Order snapshot mismatch for {$attr}");
        }

        // Assert all item-level snapshot attributes remain identical
        foreach ($itemASnapshots as $attr => $expectedVal) {
            $this->assertEquals($expectedVal, $reloadedItemA->{$attr}, "Item snapshot mismatch for {$attr}");
        }

        // Assert Admin Show page renders successfully with 200 OK and renders snapshot text
        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $reloadedOrder));
        $response->assertOk();
        $response->assertSee('خصم 10% على منتج أ');
        $response->assertSee('حزمة التوفير');
        $response->assertSee('كوبون ترحيبي');
        $response->assertSee('WELCOME200');
        $response->assertSee(number_format($reloadedOrder->total, 2));

        // Downstream Test: Sales Return calculates against confirmed net historical price
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variantA->id,
            'movement_type' => StockMovementType::Sale,
            'direction' => StockMovementDirection::Out,
            'quantity' => 2.0,
            'reference_type' => 'Order',
            'reference_id' => $reloadedOrder->id,
            'occurred_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $returnData = new SalesReturnData(
            orderId: $reloadedOrder->id,
            warehouseId: $this->warehouse->id,
            reasonCode: 'customer_changed_mind',
            notes: 'إرجاع وحدة واحدة',
            items: [
                new SalesReturnItemData(
                    orderItemId: $reloadedItemA->id,
                    productVariantId: $this->variantA->id,
                    returnQuantity: 1.0,
                ),
            ]
        );

        $salesReturn = app(CreateSalesReturnAction::class)->execute($returnData, $this->admin->id);
        $postedReturn = app(PostSalesReturnAction::class)->execute($salesReturn, $this->admin->id);

        $this->assertSame(SalesReturnStatus::Posted, $postedReturn->status);
        // Net unit price for itemA = line_total (which was discounted) / quantity (2)
        // Original price 5000 is IGNORED; historical net price is preserved!
        $expectedNetReturnUnitPrice = round((float) $reloadedItemA->line_total / (float) $reloadedItemA->quantity, 4);
        $this->assertEquals($expectedNetReturnUnitPrice, (float) $postedReturn->total);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
