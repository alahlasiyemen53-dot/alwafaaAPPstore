<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
    }

    public function test_admin_can_create_currency_and_only_one_default_remains(): void
    {
        $token = $this->adminToken();
        $response = $this->withToken($token)->postJson('/api/v1/admin/currencies', [
            'code' => 'EUR', 'name_ar' => 'يورو', 'name_en' => 'Euro', 'decimal_places' => 2, 'is_default' => true,
        ]);

        $response->assertCreated();
        $this->assertSame(1, Currency::query()->where('is_default', true)->count());
        $this->assertTrue(Currency::query()->where('code', 'EUR')->value('is_default'));
        $this->assertFalse(Currency::query()->where('code', 'YER')->value('is_default'));
    }

    public function test_exchange_rate_rejects_same_currency_and_non_positive_rate(): void
    {
        $token = $this->adminToken();
        $currency = Currency::query()->firstOrFail();
        $this->withToken($token)->postJson('/api/v1/admin/exchange-rates', [
            'from_currency_id' => $currency->id, 'to_currency_id' => $currency->id, 'rate' => 0, 'effective_at' => now()->toISOString(),
        ])->assertUnprocessable();
    }

    public function test_location_hierarchy_and_category_cycle_are_protected(): void
    {
        $token = $this->adminToken();
        $governorate = Governorate::query()->firstOrFail();
        $city = $this->withToken($token)->postJson('/api/v1/admin/cities', [
            'governorate_id' => $governorate->id, 'name_ar' => 'مدينة اختبار', 'slug' => 'test-city',
        ])->assertCreated()->json('data.id');
        $otherGovernorate = Governorate::query()->whereKeyNot($governorate->id)->firstOrFail();
        $this->withToken($token)->postJson('/api/v1/admin/warehouses', [
            'code' => 'WH-1', 'name_ar' => 'مستودع', 'governorate_id' => $otherGovernorate->id, 'city_id' => $city,
        ])->assertStatus(409);

        $parent = $this->withToken($token)->postJson('/api/v1/admin/categories', [
            'name_ar' => 'Parent', 'slug' => 'parent',
        ])->assertCreated()->json('data.id');
        $child = $this->withToken($token)->postJson('/api/v1/admin/categories', [
            'name_ar' => 'Child', 'slug' => 'child', 'parent_id' => $parent,
        ])->assertCreated()->json('data.id');
        $this->withToken($token)->patchJson("/api/v1/admin/categories/$parent", [
            'name_ar' => 'Parent', 'slug' => 'parent', 'parent_id' => $child,
        ])->assertStatus(409);
    }

    public function test_unknown_settings_are_rejected_and_unauthorized_user_is_forbidden(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->putJson('/api/v1/admin/settings', ['settings' => ['unknown_key' => 'x']])->assertUnprocessable();

        $support = User::factory()->create(['email' => 'support-md@example.com']);
        $support->syncRoles([Role::query()->where('name', 'customer_support')->firstOrFail()]);
        $limitedToken = $support->createToken('test')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($limitedToken)->getJson('/api/v1/admin/currencies')->assertForbidden();
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        return $admin->createToken('test')->plainTextToken;
    }
}
