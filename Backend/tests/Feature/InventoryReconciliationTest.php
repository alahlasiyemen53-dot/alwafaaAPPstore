<?php

namespace Tests\Feature;

use App\Actions\RecordStockMovementAction;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-REC', 'name_ar' => 'مستودع المطابقة', 'is_active' => true]);

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'علامة 1', 'slug' => 'brand-1', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف 1', 'slug' => 'cat-1', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'حليب مجفف',
            'slug' => 'milk-powder',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MILK-001',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_reconciliation_command_succeeds_when_movements_match(): void
    {
        $action = app(RecordStockMovementAction::class);

        // Movement 1: +50
        $action->execute(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            movementType: StockMovementType::AdjustmentIn,
            direction: StockMovementDirection::In,
            quantity: 50,
            reference: $this->warehouse,
        );

        // Movement 2: -15
        $action->execute(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            movementType: StockMovementType::AdjustmentOut,
            direction: StockMovementDirection::Out,
            quantity: 15,
            reference: $this->warehouse,
        );

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('اكتملت المطابقة بنجاح')
            ->assertExitCode(0);
    }

    public function test_reconciliation_command_detects_discrepancy(): void
    {
        $action = app(RecordStockMovementAction::class);

        $action->execute(
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            movementType: StockMovementType::AdjustmentIn,
            direction: StockMovementDirection::In,
            quantity: 50,
            reference: $this->warehouse,
        );

        // Manually tamper inventory row to introduce a mismatch
        Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['quantity' => 999]);

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('تم العثور على (1) فروقات')
            ->assertExitCode(1);
    }
}
