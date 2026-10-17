<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminInventoryWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name_ar' => 'المستودع الرئيسي',
            'is_active' => true,
        ]);

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
            'name_ar' => 'منتج تجريبي للمخزون',
            'slug' => 'test-inv-product',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-INV-001',
            'barcode' => '628100000001',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->admin = User::create([
            'name' => 'مسؤول المستودع',
            'email' => 'wh-admin@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->addRole(Role::where('name', 'super_admin')->first());
    }

    public function test_guest_is_redirected_from_inventory(): void
    {
        $response = $this->get(route('admin.inventory.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permission_cannot_access_inventory(): void
    {
        $user = User::create([
            'name' => 'مستخدم عادي',
            'email' => 'regular@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.inventory.index'));
        $response->assertForbidden();
    }

    public function test_authorized_admin_can_view_inventory_balances_and_filters(): void
    {
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 100.0000,
            'reserved_quantity' => 20.0000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();
        $response->assertSee('منتج تجريبي للمخزون');
        $response->assertSee('SKU-INV-001');
        $response->assertSee('100.00'); // On hand
        $response->assertSee('20.00');  // Reserved
        $response->assertSee('80.00');  // Available

        // Filter by warehouse
        $responseFilter = $this->actingAs($this->admin)->get(route('admin.inventory.index', [
            'warehouse_id' => $this->warehouse->id,
            'stock_status' => 'in_stock',
            'search' => 'SKU-INV-001',
        ]));
        $responseFilter->assertOk();
        $responseFilter->assertSee('SKU-INV-001');
    }
}
