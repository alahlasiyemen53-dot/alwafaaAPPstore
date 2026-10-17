<?php

namespace Tests\Feature;

use App\Actions\AddProductToCustomerWishlistAction;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminWishlistWebTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer1;

    private Customer $customer2;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->customer1 = Customer::create([
            'name' => 'ريم أحمد',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
        ]);

        $this->customer2 = Customer::create([
            'name' => 'هدى صالح',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'مكياج',
            'slug' => 'makeup',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name_ar' => 'ماك',
            'slug' => 'mac',
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'أحمر شفاه كلاسيكي',
            'slug' => 'classic-lipstick',
            'status' => 'active',
            'has_variants' => false,
        ]);

        $this->product2 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'ظل عيون مخملي',
            'slug' => 'velvet-eyeshadow',
            'status' => 'inactive',
            'has_variants' => false,
        ]);

        $action = new AddProductToCustomerWishlistAction;
        $action->execute($this->customer1, $this->product1);
        $action->execute($this->customer2, $this->product2);
    }

    public function test_guest_is_redirected_from_wishlists(): void
    {
        $this->get('/admin/wishlists')->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_view_wishlists(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get('/admin/wishlists')->assertForbidden();
    }

    public function test_authorized_staff_can_view_wishlists_index(): void
    {
        $admin = $this->userWithRole('admin');
        $support = $this->userWithRole('customer_support');
        $ops = $this->userWithRole('operations_manager');

        $response = $this->actingAs($admin)->get('/admin/wishlists');
        $response->assertOk();
        $response->assertSee('ريم أحمد');
        $response->assertSee('أحمر شفاه كلاسيكي');
        $response->assertSee('هدى صالح');
        $response->assertSee('ظل عيون مخملي');

        $this->actingAs($support)->get('/admin/wishlists')->assertOk();
        $this->actingAs($ops)->get('/admin/wishlists')->assertOk();
    }

    public function test_wishlists_can_be_filtered_by_search_term(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get('/admin/wishlists?search=ريم');
        $response->assertOk();
        $response->assertSee('ريم أحمد');
        $response->assertDontSee('هدى صالح');

        $response = $this->actingAs($admin)->get('/admin/wishlists?search=مخملي');
        $response->assertOk();
        $response->assertSee('ظل عيون مخملي');
        $response->assertDontSee('أحمر شفاه كلاسيكي');
    }

    public function test_wishlists_can_be_filtered_by_product_status(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get('/admin/wishlists?product_status=active');
        $response->assertOk();
        $response->assertSee('أحمر شفاه كلاسيكي');
        $response->assertDontSee('ظل عيون مخملي');

        $response = $this->actingAs($admin)->get('/admin/wishlists?product_status=inactive');
        $response->assertOk();
        $response->assertSee('ظل عيون مخملي');
        $response->assertDontSee('أحمر شفاه كلاسيكي');
    }

    public function test_customer_detail_displays_wishlist_section(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get("/admin/customers/{$this->customer1->id}");
        $response->assertOk();
        $response->assertSee('قائمة الرغبات / المفضلة');
        $response->assertSee('أحمر شفاه كلاسيكي');
    }

    public function test_admin_routes_are_strictly_read_only(): void
    {
        $admin = $this->userWithRole('admin');

        // Mutation attempts must be rejected (405 Method Not Allowed on collection route, 404 on unmapped item routes)
        $this->actingAs($admin)->post('/admin/wishlists', ['customer_id' => 1, 'product_id' => 1])->assertMethodNotAllowed();
        $this->actingAs($admin)->delete('/admin/wishlists/1')->assertNotFound();
        $this->actingAs($admin)->put('/admin/wishlists/1', [])->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $userRole = Role::where('name', $role)->firstOrFail();
        $user->roles()->sync([$userRole->id]);

        return $user;
    }
}
