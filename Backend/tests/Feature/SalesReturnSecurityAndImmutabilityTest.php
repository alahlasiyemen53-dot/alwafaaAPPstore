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

final class SalesReturnSecurityAndImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

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

        $this->cashier = User::factory()->create();
        $this->cashier->addRole('cashier'); // Cashier has no sales_returns permissions

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
            'slug' => 'dairy-sec',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name_ar' => 'حليب الممتاز',
            'slug' => 'milk-premier-sec',
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

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 20.0,
            'reserved_quantity' => 0.0,
        ]);

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

        // Record physical SALE OUT
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
    }

    public function test_unauthorized_user_cannot_view_or_create_sales_returns(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('admin.sales-returns.index'));
        $response->assertForbidden();

        $createResponse = $this->actingAs($this->cashier)->get(route('admin.sales-returns.create'));
        $createResponse->assertForbidden();
    }

    public function test_cross_order_item_injection_is_strictly_rejected(): void
    {
        // Create a second order with another order item
        $createOrderAction = app(CreateOrderAction::class);
        $secondOrder = $createOrderAction->execute(
            new CreateOrderData(
                customerId: $this->customer->id,
                warehouseId: $this->warehouse->id,
                currencyId: $this->currency->id,
                customerAddressId: $this->customer->addresses->first()->id,
                notes: 'طلب آخر',
                items: [
                    new OrderItemData(
                        productVariantId: $this->variant->id,
                        quantity: 5.0,
                    ),
                ]
            ),
            $this->admin->id
        );

        $foreignOrderItem = $secondOrder->items->first();

        $createReturnAction = app(CreateSalesReturnAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('غير تابع للطلب المحدد');

        // Trying to return order_1 with an item from order_2
        $createReturnAction->execute(
            new SalesReturnData(
                orderId: $this->order->id,
                warehouseId: $this->warehouse->id,
                reasonCode: 'wrong_item',
                notes: 'محاولة حقن بند من طلب آخر',
                items: [
                    new SalesReturnItemData(
                        orderItemId: $foreignOrderItem->id,
                        productVariantId: $this->variant->id,
                        returnQuantity: 2.0,
                    ),
                ]
            ),
            $this->admin->id
        );
    }

    public function test_posted_sales_return_is_immutable_and_cannot_be_re_posted(): void
    {
        $createReturnAction = app(CreateSalesReturnAction::class);
        $postReturnAction = app(PostSalesReturnAction::class);

        $orderItem = $this->order->items->first();

        $salesReturn = $createReturnAction->execute(
            new SalesReturnData(
                orderId: $this->order->id,
                warehouseId: $this->warehouse->id,
                reasonCode: 'customer_changed_mind',
                notes: 'مسودة للإرجاع',
                items: [
                    new SalesReturnItemData(
                        orderItemId: $orderItem->id,
                        productVariantId: $this->variant->id,
                        returnQuantity: 5.0,
                    ),
                ]
            ),
            $this->admin->id
        );

        $posted = $postReturnAction->execute($salesReturn, $this->admin->id);
        $this->assertSame(SalesReturnStatus::Posted, $posted->status);

        // Double post must be idempotent and not increase stock again
        $invBefore = Inventory::where('warehouse_id', $this->warehouse->id)->where('product_variant_id', $this->variant->id)->first()->quantity;
        $secondPost = $postReturnAction->execute($posted, $this->admin->id);
        $invAfter = Inventory::where('warehouse_id', $this->warehouse->id)->where('product_variant_id', $this->variant->id)->first()->quantity;

        $this->assertEquals($invBefore, $invAfter);
        $this->assertSame(SalesReturnStatus::Posted, $secondPost->status);
    }
}
