<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminMasterDataWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
    }

    public function test_authorized_admin_can_manage_master_data_from_blade(): void
    {
        $admin = User::factory()->create(['email' => 'master-web@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $this->get('/admin/master-data/currencies')->assertOk()->assertSee('YER');
        $this->post('/admin/master-data/currencies', [
            'code' => 'AED', 'name_ar' => 'درهم إماراتي', 'decimal_places' => 2, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('currencies', ['code' => 'AED']);

        $currency = Currency::query()->where('code', 'AED')->firstOrFail();
        $this->patch("/admin/master-data/currencies/$currency->id/deactivate")->assertRedirect();
        $this->assertFalse($currency->fresh()->is_active);
    }

    public function test_deactivating_governorate_cascades_to_cities_and_districts(): void
    {
        $admin = User::factory()->create(['email' => 'gov-cascade@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $country = \App\Models\Country::query()->where('code', 'YE')->firstOrFail();
        $governorate = \App\Models\Governorate::query()->create([
            'country_id' => $country->id,
            'name_ar' => 'محافظة تعطيل',
            'name_en' => 'Deactivate Gov',
            'slug' => 'deactivate-gov-test',
            'is_active' => true,
        ]);
        $city = \App\Models\City::query()->create([
            'governorate_id' => $governorate->id,
            'name_ar' => 'مدينة',
            'slug' => 'deactivate-city-test',
            'is_active' => true,
        ]);
        $district = \App\Models\District::query()->create([
            'city_id' => $city->id,
            'name_ar' => 'مديرية',
            'slug' => 'deactivate-district-test',
            'is_active' => true,
        ]);

        $this->patch("/admin/master-data/governorates/{$governorate->id}/deactivate")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($governorate->fresh()->is_active);
        $this->assertFalse($city->fresh()->is_active);
        $this->assertFalse($district->fresh()->is_active);
    }

    public function test_deactivating_category_cascades_to_children(): void
    {
        $admin = User::factory()->create(['email' => 'cat-cascade@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $parent = \App\Models\Category::query()->create([
            'name_ar' => 'تصنيف أب',
            'slug' => 'parent-deactivate-test',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $child = \App\Models\Category::query()->create([
            'parent_id' => $parent->id,
            'name_ar' => 'تصنيف فرعي',
            'slug' => 'child-deactivate-test',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->patch("/admin/master-data/categories/{$parent->id}/deactivate")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($parent->fresh()->is_active);
        $this->assertFalse($child->fresh()->is_active);
    }

    public function test_categories_index_shows_product_count_column(): void
    {
        $admin = User::factory()->create(['email' => 'cat-count@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $category = \App\Models\Category::query()->create([
            'name_ar' => 'تصنيف بعدد أصناف',
            'slug' => 'category-with-products-count',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        \App\Models\Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج أول',
            'slug' => 'product-count-one',
            'status' => 'active',
        ]);
        \App\Models\Product::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'منتج ثانٍ',
            'slug' => 'product-count-two',
            'status' => 'active',
        ]);

        $this->get('/admin/master-data/categories')
            ->assertOk()
            ->assertSee('عدد الأصناف')
            ->assertSee('تصنيف بعدد أصناف')
            ->assertSee('>2<', false);
    }

    public function test_admin_can_toggle_delivery_for_countries_and_governorates(): void
    {
        $admin = User::factory()->create(['email' => 'delivery-toggle@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        // Create country (e.g., Saudi Arabia) active for registration but delivery inactive
        $sa = \App\Models\Country::query()->create([
            'code' => 'SA',
            'name_ar' => 'المملكة العربية السعودية',
            'name_en' => 'Saudi Arabia',
            'calling_code' => '+966',
            'sort_order' => 2,
            'is_active' => true,
            'is_delivery_active' => false,
        ]);

        $this->get('/admin/master-data/countries')
            ->assertOk()
            ->assertSee('التوصيل')
            ->assertSee('المملكة العربية السعودية');

        // Toggle delivery activate for SA
        $this->patch("/admin/master-data/countries/{$sa->id}/delivery-activate")
            ->assertRedirect();
        $this->assertTrue($sa->fresh()->is_delivery_active);

        // Toggle delivery deactivate for SA
        $this->patch("/admin/master-data/countries/{$sa->id}/delivery-deactivate")
            ->assertRedirect();
        $this->assertFalse($sa->fresh()->is_delivery_active);

        // Governorate delivery toggle
        $country = \App\Models\Country::query()->where('code', 'YE')->firstOrFail();
        $gov = \App\Models\Governorate::query()->create([
            'country_id' => $country->id,
            'name_ar' => 'صنعاء الفحص',
            'name_en' => 'Sanaa Test',
            'slug' => 'sanaa-delivery-toggle-test',
            'is_active' => true,
            'is_delivery_active' => true,
        ]);

        $this->get('/admin/master-data/governorates')
            ->assertOk()
            ->assertSee('التوصيل')
            ->assertSee('صنعاء الفحص');

        // Deactivate delivery for gov
        $this->patch("/admin/master-data/governorates/{$gov->id}/delivery-deactivate")
            ->assertRedirect();
        $this->assertFalse($gov->fresh()->is_delivery_active);

        // Activate delivery for gov
        $this->patch("/admin/master-data/governorates/{$gov->id}/delivery-activate")
            ->assertRedirect();
        $this->assertTrue($gov->fresh()->is_delivery_active);
    }

    public function test_user_without_master_data_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['email' => 'limited-master-web@example.com']);
        $user->syncRoles([Role::query()->where('name', 'customer_support')->firstOrFail()]);
        $this->actingAs($user)->get('/admin/master-data/currencies')->assertForbidden();
    }
}
