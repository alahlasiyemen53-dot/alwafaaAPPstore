<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AdminProductWebTest extends TestCase
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

    public function test_guest_is_redirected_from_product_admin(): void
    {
        $this->get('/admin/products')->assertRedirect('/admin/login');
    }

    public function test_user_without_product_permissions_cannot_access_records_or_actions(): void
    {
        $user = $this->userWithRole('customer_support');
        $product = $this->product();

        $this->actingAs($user)->get('/admin/products')->assertForbidden();
        $this->actingAs($user)->get("/admin/products/{$product->id}")->assertForbidden();
        $this->actingAs($user)->put("/admin/products/{$product->id}", $this->validPayload())->assertForbidden();
        $this->actingAs($user)->patch("/admin/products/{$product->id}/activate")->assertForbidden();
    }

    public function test_authorized_admin_completes_product_workflow_in_velzon_ui(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->get('/admin/products/create')->assertOk()->assertSee('dir="rtl"', false)->assertSee('المعلومات الأساسية');
        $this->post('/admin/products', $this->validPayload())->assertRedirect();
        $product = Product::query()->where('slug', 'serum')->firstOrFail();

        $this->get("/admin/products/{$product->id}")->assertOk()->assertSee('سيروم البشرة')->assertSee('علامة لمّة');
        $this->put("/admin/products/{$product->id}", $this->validPayload(['name_ar' => 'سيروم مطور', 'status' => 'inactive']))->assertRedirect();
        self::assertSame(ProductStatus::Draft, $product->fresh()->status);
        $this->patch("/admin/products/{$product->id}/activate")->assertRedirect();
        self::assertSame(ProductStatus::Active, $product->fresh()->status);
        $this->patch("/admin/products/{$product->id}/deactivate")->assertRedirect();
        self::assertSame(ProductStatus::Inactive, $product->fresh()->status);
    }

    #[DataProvider('invalidProductCases')]
    public function test_invalid_product_input_is_rejected(string $field, mixed $invalidValue): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $this->post('/admin/products', $this->validPayload([$field => $invalidValue]))
            ->assertSessionHasErrors($field);
    }

    public static function invalidProductCases(): array
    {
        return [
            'unknown brand' => ['brand_id', 999999],
            'missing brand' => ['brand_id', ''],
            'unknown category' => ['category_id', 999999],
            'unsafe slug' => ['slug', 'Bad Slug!'],
            'unknown status' => ['status', 'published'],
        ];
    }

    public function test_duplicate_slug_is_rejected_without_overwriting_existing_product(): void
    {
        $this->product(['slug' => 'serum']);

        $this->actingAs($this->userWithRole('admin'))->post('/admin/products', $this->validPayload())
            ->assertSessionHasErrors('slug');
        self::assertSame(1, Product::query()->where('slug', 'serum')->count());
    }

    public function test_duplicate_arabic_name_is_rejected_and_does_not_create_second_product(): void
    {
        $this->product(['name_ar' => 'سيروم البشرة', 'slug' => 'serum-existing']);

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/products', $this->validPayload(['slug' => 'serum-new']))
            ->assertSessionHasErrors('name_ar');

        self::assertSame(1, Product::query()->where('name_ar', 'سيروم البشرة')->count());
    }

    public function test_listing_searches_filters_and_sorts_without_lazy_loading(): void
    {
        $otherCategory = Category::query()->create(['name_ar' => 'العطور', 'slug' => 'perfume', 'sort_order' => 2, 'is_active' => true]);
        foreach (range(1, 21) as $number) {
            $this->product([
                'name_ar' => sprintf('منتج %02d', $number),
                'slug' => "product-$number",
                'status' => $number === 21 ? ProductStatus::Active : ProductStatus::Draft,
                'category_id' => $number === 21 ? $otherCategory->id : $this->category->id,
            ]);
        }

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/products?search=21&category_id='.$otherCategory->id.'&status=active&sort=name_ar&direction=asc')
            ->assertOk()->assertSee('منتج 21')->assertDontSee('منتج 20');
    }

    public function test_listing_paginates_twenty_products_per_page(): void
    {
        foreach (range(1, 21) as $number) {
            $this->product(['name_ar' => sprintf('منتج %02d', $number), 'slug' => "page-product-$number"]);
        }

        $this->actingAs($this->userWithRole('admin'));
        $this->get('/admin/products')->assertOk()->assertDontSee('منتج 01');
        $this->get('/admin/products?page=2')->assertOk()->assertSee('منتج 01');
    }

    public function test_product_text_is_escaped_in_list_and_details(): void
    {
        $product = $this->product(['name_ar' => '<script>alert(1)</script>', 'slug' => 'escaped-product']);
        $this->actingAs($this->userWithRole('admin'));

        $this->get('/admin/products')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get("/admin/products/{$product->id}")->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_product_actions_are_hidden_from_read_only_user(): void
    {
        $role = Role::query()->where('name', 'customer_support')->firstOrFail();
        $role->syncPermissions(Permission::query()->where('name', 'admin.products.view')->get());
        $product = $this->product();

        $this->actingAs($this->userWithRole('customer_support'))->get('/admin/products')
            ->assertOk()->assertSee($product->name_ar)->assertDontSee('منتج جديد')->assertDontSee('تعديل '.$product->name_ar);
    }

    public function test_repeated_lifecycle_request_returns_clear_business_feedback(): void
    {
        $product = $this->product(['status' => ProductStatus::Active]);

        $this->actingAs($this->userWithRole('admin'))->patch("/admin/products/{$product->id}/activate")
            ->assertRedirect()->assertSessionHas('error', 'المنتج نشط بالفعل.');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'سيروم البشرة',
            'name_en' => 'Skin Serum',
            'slug' => 'serum',
            'description_ar' => 'وصف آمن',
            'description_en' => 'Safe description',
            'status' => 'draft',
            'has_variants' => '1',
            'stock' => 500,
            'sku' => 'FORBIDDEN-SKU',
        ], $overrides);
    }

    private function product(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج اختبار',
            'slug' => 'test-product',
            'status' => ProductStatus::Draft,
            'has_variants' => false,
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
