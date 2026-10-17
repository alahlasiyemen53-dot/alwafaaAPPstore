<?php

namespace Tests\Feature;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminStockAdjustmentWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-ADJ', 'name_ar' => 'مستودع التسويات', 'is_active' => true]);

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
            'name_ar' => 'زيت طبخ',
            'slug' => 'cooking-oil',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'OIL-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->admin = User::create([
            'name' => 'مسؤول الجرد',
            'email' => 'adj-admin@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->addRole(Role::where('name', 'super_admin')->first());
    }

    public function test_post_physical_count_gain_increases_stock(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'reason_code' => StockAdjustmentReason::PhysicalCountGain->value,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'direction' => 'in',
                    'quantity' => 15,
                ],
            ],
        ]);

        $adjustment = StockAdjustment::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.post', $adjustment));

        $adjustment->refresh();
        $this->assertSame(StockAdjustmentStatus::Posted, $adjustment->status);

        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals(15.0, (float) $inventory->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::AdjustmentIn->value,
            'direction' => StockMovementDirection::In->value,
            'quantity' => 15.0,
        ]);
    }

    public function test_post_damaged_stock_write_off_decreases_stock(): void
    {
        // Existing stock of 25
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 25.0,
            'reserved_quantity' => 0.0,
        ]);

        // Write-off 5 damaged units
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'reason_code' => StockAdjustmentReason::Damaged->value,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'direction' => 'out',
                    'quantity' => 5,
                ],
            ],
        ]);

        $adjustment = StockAdjustment::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.post', $adjustment));

        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals(20.0, (float) $inventory->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::Damaged->value,
            'direction' => StockMovementDirection::Out->value,
            'quantity' => 5.0,
        ]);
    }

    public function test_adjustment_out_exceeding_current_stock_fails(): void
    {
        // Current stock is only 2
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2.0,
            'reserved_quantity' => 0.0,
        ]);

        // Attempting to write off 10
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'reason_code' => StockAdjustmentReason::PhysicalCountLoss->value,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'direction' => 'out',
                    'quantity' => 10,
                ],
            ],
        ]);

        $adjustment = StockAdjustment::firstOrFail();
        $response = $this->actingAs($this->admin)->post(route('admin.stock-adjustments.post', $adjustment));
        $response->assertSessionHas('error');

        $adjustment->refresh();
        $this->assertSame(StockAdjustmentStatus::Draft, $adjustment->status);
    }
}
