<?php

namespace Tests\Feature;

use App\Actions\CreateOrderAction;
use App\Actions\CreateSalesReturnAction;
use App\Actions\PostSalesReturnAction;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\DTOs\SalesReturnData;
use App\DTOs\SalesReturnItemData;
use App\Enums\ProductVariantStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesReturnPostingAndStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->addRole('super_admin');

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'is_default' => true,
            'is_active' => true,
            'decimal_places' => 2,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'محمد العليمي',
            'phone' => '777111222',
            'normalized_phone' => '+967777111222',
            'is_active' => true,
        ]);

        CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد',
            'contact_phone' => '777111222',
            'neighborhood' => 'حدة',
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'ألبان',
            'slug' => 'dairy',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name_ar' => 'حليب الممتاز',
            'slug' => 'milk-premier',
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MILK-001',
            'status' => ProductVariantStatus::Active,
        ]);

        $this->variant->prices()->create([
            'currency_id' => $this->currency->id,
            'price' => 100.0,
        ]);

        // Inventory on hand = 50
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 50.0,
            'reserved_quantity' => 0.0,
        ]);

        // Create order for 10 items
        $createOrderAction = app(CreateOrderAction::class);
        $this->order = $createOrderAction->execute(
            new CreateOrderData(
                customerId: $this->customer->id,
                warehouseId: $this->warehouse->id,
                currencyId: $this->currency->id,
                customerAddressId: $this->customer->addresses->first()->id,
                notes: 'طلب تجريبي',
                items: [
                    new OrderItemData(
                        productVariantId: $this->variant->id,
                        quantity: 10.0,
                    ),
                ]
            ),
            $this->admin->id
        );
    }

    public function test_cannot_post_sales_return_when_no_physical_sale_out_movement_exists(): void
    {
        // Order exists and confirmed/draft, but no physical SALE OUT occurred yet
        $createReturnAction = app(CreateSalesReturnAction::class);
        $postReturnAction = app(PostSalesReturnAction::class);

        $orderItem = $this->order->items->first();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('تتجاوز الكمية المتبقية القابلة للإرجاع');

        $returnData = new SalesReturnData(
            orderId: $this->order->id,
            warehouseId: $this->warehouse->id,
            reasonCode: 'customer_changed_mind',
            notes: 'محاولة إرجاع بضاعة لم تخرج فعلياً',
            items: [
                new SalesReturnItemData(
                    orderItemId: $orderItem->id,
                    productVariantId: $this->variant->id,
                    returnQuantity: 5.0,
                ),
            ]
        );

        $createReturnAction->execute($returnData, $this->admin->id);
    }

    public function test_post_sales_return_increases_inventory_and_creates_stock_movement(): void
    {
        $orderItem = $this->order->items->first();

        // 1. Simulate physical SALE OUT of 10 units
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::Sale,
            'direction' => StockMovementDirection::Out,
            'quantity' => 10.0,
            'reference_type' => 'Order',
            'reference_id' => $this->order->id,
            'occurred_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        // Inventory was reduced to 40
        $inv = Inventory::where('warehouse_id', $this->warehouse->id)->where('product_variant_id', $this->variant->id)->first();
        $inv->quantity = 40.0;
        $inv->save();

        // 2. Create and Post partial return of 3 units
        $createReturnAction = app(CreateSalesReturnAction::class);
        $postReturnAction = app(PostSalesReturnAction::class);

        $salesReturn = $createReturnAction->execute(
            new SalesReturnData(
                orderId: $this->order->id,
                warehouseId: $this->warehouse->id,
                reasonCode: 'wrong_item',
                notes: 'صنف غير مطابق تم استلامه',
                items: [
                    new SalesReturnItemData(
                        orderItemId: $orderItem->id,
                        productVariantId: $this->variant->id,
                        returnQuantity: 3.0,
                    ),
                ]
            ),
            $this->admin->id
        );

        $this->assertSame(SalesReturnStatus::Draft, $salesReturn->status);
        // While Draft, inventory is still 40
        $this->assertEquals(40.0, (float) $inv->fresh()->quantity);

        // POST return
        $postedReturn = $postReturnAction->execute($salesReturn, $this->admin->id);

        $this->assertSame(SalesReturnStatus::Posted, $postedReturn->status);
        // Inventory must increase to 43
        $this->assertEquals(43.0, (float) $inv->fresh()->quantity);

        // StockMovement created
        $movement = StockMovement::where('reference_type', 'SalesReturn')
            ->where('reference_id', $postedReturn->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::SaleReturn, $movement->movement_type);
        $this->assertSame(StockMovementDirection::In, $movement->direction);
        $this->assertEquals(3.0, (float) $movement->quantity);

        // 3. Post second return of 4 units
        $secondReturn = $createReturnAction->execute(
            new SalesReturnData(
                orderId: $this->order->id,
                warehouseId: $this->warehouse->id,
                reasonCode: 'defective_item',
                notes: 'إرجاع ثاني',
                items: [
                    new SalesReturnItemData(
                        orderItemId: $orderItem->id,
                        productVariantId: $this->variant->id,
                        returnQuantity: 4.0,
                    ),
                ]
            ),
            $this->admin->id
        );

        $postReturnAction->execute($secondReturn, $this->admin->id);
        $this->assertEquals(47.0, (float) $inv->fresh()->quantity);

        // 4. Try to return 4 more units (only 3 remaining out of 10) -> Must fail
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('تتجاوز الكمية المتبقية القابلة للإرجاع (3.0000)');

        $createReturnAction->execute(
            new SalesReturnData(
                orderId: $this->order->id,
                warehouseId: $this->warehouse->id,
                reasonCode: 'other',
                notes: 'محاولة إرجاع زائد',
                items: [
                    new SalesReturnItemData(
                        orderItemId: $orderItem->id,
                        productVariantId: $this->variant->id,
                        returnQuantity: 4.0,
                    ),
                ]
            ),
            $this->admin->id
        );
    }
}
