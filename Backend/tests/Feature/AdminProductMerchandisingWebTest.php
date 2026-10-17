<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminProductMerchandisingWebTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->brand = Brand::query()->create(['name_ar' => 'علامة لمّة', 'slug' => 'lamma-brand', 'is_active' => true]);
        $this->category = Category::query()->create(['name_ar' => 'العناية', 'slug' => 'care', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_admin_can_create_product_with_merchandising_flags(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $response = $this->post('/admin/products', [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'مرطب فاخر',
            'slug' => 'luxury-moisturizer',
            'status' => 'active',
            'is_best_seller' => '1',
            'is_new' => '1',
            'is_exclusive' => '0',
        ]);

        $response->assertRedirect();

        $product = Product::query()->where('slug', 'luxury-moisturizer')->firstOrFail();
        self::assertTrue($product->is_best_seller);
        self::assertTrue($product->is_new);
        self::assertFalse($product->is_exclusive);
    }

    public function test_admin_can_update_product_merchandising_flags(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $product = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'غسول وجه',
            'slug' => 'face-wash',
            'is_best_seller' => false,
            'is_new' => false,
            'is_exclusive' => false,
        ]);

        $response = $this->put("/admin/products/{$product->id}", [
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'غسول وجه مطور',
            'slug' => 'face-wash',
            'is_best_seller' => '1',
            'is_new' => '0',
            'is_exclusive' => '1',
        ]);

        $response->assertRedirect();

        $fresh = $product->fresh();
        self::assertTrue($fresh->is_best_seller);
        self::assertFalse($fresh->is_new);
        self::assertTrue($fresh->is_exclusive);
    }

    public function test_admin_product_index_filters_by_merchandising_flags(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $bestSellerOnly = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج مبيعات مرتفعة',
            'slug' => 'bs-prod',
            'is_best_seller' => true,
            'is_new' => false,
            'is_exclusive' => false,
        ]);

        $exclusiveOnly = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج حصري للغاية',
            'slug' => 'ex-prod',
            'is_best_seller' => false,
            'is_new' => false,
            'is_exclusive' => true,
        ]);

        // Filter best_seller = yes
        $resBS = $this->get('/admin/products?best_seller=yes');
        $resBS->assertOk();
        $resBS->assertSee('منتج مبيعات مرتفعة');
        $resBS->assertDontSee('منتج حصري للغاية');

        // Filter exclusive = yes
        $resEx = $this->get('/admin/products?exclusive=yes');
        $resEx->assertOk();
        $resEx->assertSee('منتج حصري للغاية');
        $resEx->assertDontSee('منتج مبيعات مرتفعة');
    }

    public function test_admin_product_views_render_merchandising_badges(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $product = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج استثنائي',
            'slug' => 'unique-item',
            'is_best_seller' => true,
            'is_new' => true,
            'is_exclusive' => true,
        ]);

        // Index page
        $indexPage = $this->get('/admin/products');
        $indexPage->assertOk();
        $indexPage->assertSee('الأكثر مبيعاً');
        $indexPage->assertSee('جديد');
        $indexPage->assertSee('حصري');

        // Show page
        $showPage = $this->get("/admin/products/{$product->id}");
        $showPage->assertOk();
        $showPage->assertSee('العرض والتسويق');
        $showPage->assertSee('الأكثر مبيعاً');
        $showPage->assertSee('جديد');
        $showPage->assertSee('حصري');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role)]
        );
        $user->addRole($roleModel);

        return $user;
    }
}
