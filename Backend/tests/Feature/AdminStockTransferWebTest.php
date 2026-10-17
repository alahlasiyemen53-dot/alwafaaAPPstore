<?php

namespace Tests\Feature;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminStockTransferWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $fromWarehouse;

    private Warehouse $toWarehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->fromWarehouse = Warehouse::create(['code' => 'WH-FROM', 'name_ar' => 'مستودع صنعاء', 'is_active' => true]);
        $this->toWarehouse = Warehouse::create(['code' => 'WH-TO', 'name_ar' => 'مستودع عدن', 'is_active' => true]);

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
            'name_ar' => 'أرز بسمتي',
            'slug' => 'rice-basmati',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RICE-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->admin = User::create([
            'name' => 'مسؤول التحويلات',
            'email' => 'tr-admin@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->addRole(Role::where('name', 'super_admin')->first());
    }

    public function test_cannot_transfer_to_same_warehouse(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.stock-transfers.store'), [
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->fromWarehouse->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 10,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('to_warehouse_id');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_posting_transfer_without_sufficient_stock_fails(): void
    {
        // Source warehouse has only 5 in stock
        Inventory::create([
            'warehouse_id' => $this->fromWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 5.0,
            'reserved_quantity' => 0.0,
        ]);

        // Attempt transfer of 10
        $storeResponse = $this->actingAs($this->admin)->post(route('admin.stock-transfers.store'), [
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 10,
                ],
            ],
        ]);
        $storeResponse->assertSessionHasNoErrors();
        $transfer = StockTransfer::firstOrFail();

        // Attempting to post should fail with domain exception
        $postResponse = $this->actingAs($this->admin)->post(route('admin.stock-transfers.post', $transfer));
        $postResponse->assertSessionHas('error');

        $transfer->refresh();
        $this->assertSame(StockTransferStatus::Draft, $transfer->status);
    }

    public function test_posting_transfer_updates_both_warehouses_and_records_matched_movements(): void
    {
        // Source warehouse has 50
        Inventory::create([
            'warehouse_id' => $this->fromWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 50.0,
            'reserved_quantity' => 0.0,
        ]);

        // Destination warehouse has 0 (or no row yet)
        $this->actingAs($this->admin)->post(route('admin.stock-transfers.store'), [
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 20,
                ],
            ],
        ]);

        $transfer = StockTransfer::firstOrFail();
        $postResponse = $this->actingAs($this->admin)->post(route('admin.stock-transfers.post', $transfer));
        $postResponse->assertRedirect(route('admin.stock-transfers.show', $transfer));

        $transfer->refresh();
        $this->assertSame(StockTransferStatus::Posted, $transfer->status);

        // Source warehouse reduced to 30
        $fromInventory = Inventory::where('warehouse_id', $this->fromWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals(30.0, (float) $fromInventory->quantity);

        // Destination warehouse increased to 20
        $toInventory = Inventory::where('warehouse_id', $this->toWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals(20.0, (float) $toInventory->quantity);

        // Verify Matched Movements: OUT and IN
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->fromWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::TransferOut->value,
            'direction' => StockMovementDirection::Out->value,
            'quantity' => 20.0,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->toWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::TransferIn->value,
            'direction' => StockMovementDirection::In->value,
            'quantity' => 20.0,
        ]);
    }
}
