<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrderAction;
use App\Actions\RecordStockMovementAction;
use App\Enums\OrderStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderReservationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل المطابقة',
            'phone' => '779900112',
            'normalized_phone' => '+967779900112',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع المطابقة',
            'code' => 'WH-REC',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'ماركة', 'slug' => 'brand-rec', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-rec', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج مطابقة',
            'slug' => 'prod-rec',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-REC-01',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 1000.00,
            'is_active' => true,
        ]);

        app(RecordStockMovementAction::class)->execute(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            movementType: StockMovementType::AdjustmentIn,
            direction: StockMovementDirection::In,
            quantity: 20,
            reference: $this->warehouse,
        );
    }

    public function test_inventory_reconcile_command_verifies_reservations(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-REC-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'subtotal' => 5000.00,
            'total' => 5000.00,
            'exchange_rate_snapshot' => 1.00000000,
            'base_currency_total' => 5000.00,
        ]);

        $order->items()->create([
            'product_variant_id' => $this->variant->id,
            'product_name_snapshot' => 'منتج مطابقة',
            'sku_snapshot' => $this->variant->sku,
            'quantity' => 5,
            'base_quantity' => 5,
            'unit_price' => 1000.00,
            'line_subtotal' => 5000.00,
            'line_total' => 5000.00,
            'base_currency_unit_price' => 1000.00,
            'base_currency_line_total' => 5000.00,
        ]);

        app(ConfirmOrderAction::class)->execute($order);

        // Run reconciliation command -> should succeed with 100% match
        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('اكتملت المطابقة بنجاح')
            ->assertExitCode(0);

        // Tamper with reserved_quantity artificially
        Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['reserved_quantity' => 99.0000]);

        // Run reconciliation command -> should fail and detect discrepancy
        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('تم العثور على')
            ->assertExitCode(1);
    }
}
