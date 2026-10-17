<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DynamicCountriesAdminAndApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
    }

    public function test_countries_api_exposes_iso_code_and_sorted_active_only(): void
    {
        $yemen = Country::query()->where('code', 'YE')->firstOrFail();
        $yemen->update(['sort_order' => 1, 'is_active' => true]);

        $inactive = Country::query()->create([
            'code' => 'XX',
            'name_ar' => 'دولة معطلة',
            'name_en' => 'Inactive',
            'calling_code' => '+999',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $activeOther = Country::query()->create([
            'code' => 'SA',
            'name_ar' => 'السعودية',
            'name_en' => 'Saudi Arabia',
            'calling_code' => '+966',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response = $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonPath('status', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($yemen->id, $ids);
        $this->assertContains($activeOther->id, $ids);
        $this->assertNotContains($inactive->id, $ids);

        $first = collect($response->json('data'))->firstWhere('id', $yemen->id);
        $this->assertSame('YE', $first['iso_code']);
        $this->assertSame('+967', $first['calling_code']);
        $this->assertArrayHasKey('name', $first);
        $this->assertSame($yemen->id, $response->json('data.0.id'));
    }

    public function test_admin_can_create_country_with_iso_and_sort_order(): void
    {
        $admin = User::factory()->create(['email' => 'country-create@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $this->post('/admin/master-data/countries', [
            'code' => 'OM',
            'name_ar' => 'عُمان',
            'name_en' => 'Oman',
            'calling_code' => '+968',
            'sort_order' => 3,
            'is_active' => 1,
        ])->assertRedirect(route('admin.master-data.countries.index'));

        $this->assertDatabaseHas('countries', [
            'code' => 'OM',
            'calling_code' => '+968',
            'sort_order' => 3,
            'is_active' => 1,
        ]);
    }

    public function test_safe_delete_rejects_country_with_governorates_and_allows_unused(): void
    {
        $admin = User::factory()->create(['email' => 'country-delete@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $used = Country::query()->create([
            'code' => 'QA',
            'name_ar' => 'قطر',
            'name_en' => 'Qatar',
            'calling_code' => '+974',
            'is_active' => true,
            'sort_order' => 8,
        ]);
        Governorate::query()->create([
            'country_id' => $used->id,
            'name_ar' => 'الدوحة',
            'slug' => 'doha-test',
            'is_active' => true,
        ]);

        $this->delete(route('admin.master-data.countries.destroy', $used->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('countries', ['id' => $used->id]);

        $unused = Country::query()->create([
            'code' => 'BH',
            'name_ar' => 'البحرين',
            'name_en' => 'Bahrain',
            'calling_code' => '+973',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $this->delete(route('admin.master-data.countries.destroy', $unused->id))
            ->assertRedirect(route('admin.master-data.countries.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('countries', ['id' => $unused->id]);
    }

    public function test_otp_rejects_inactive_country_id(): void
    {
        $inactive = Country::query()->create([
            'code' => 'ZZ',
            'name_ar' => 'معطلة',
            'name_en' => 'Disabled',
            'calling_code' => '+111',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '771234567',
            'country_id' => $inactive->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);
    }

    public function test_safe_delete_rejects_country_linked_to_customer(): void
    {
        $admin = User::factory()->create(['email' => 'country-cust-del@example.com', 'is_active' => true]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $country = Country::query()->create([
            'code' => 'KW',
            'name_ar' => 'الكويت',
            'name_en' => 'Kuwait',
            'calling_code' => '+965',
            'is_active' => true,
            'sort_order' => 7,
        ]);

        Customer::query()->create([
            'name' => 'عميل',
            'phone' => '90000001',
            'normalized_phone' => '+96590000001',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        $this->delete(route('admin.master-data.countries.destroy', $country->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('countries', ['id' => $country->id]);
    }
}
