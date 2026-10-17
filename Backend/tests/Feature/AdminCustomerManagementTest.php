<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_guest_is_redirected_from_customers(): void
    {
        $this->get('/admin/customers')->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_access_customers(): void
    {
        $user = $this->userWithRole('cashier');

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $this->actingAs($user)->get('/admin/customers')->assertForbidden();
        $this->actingAs($user)->get('/admin/customers/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/customers', ['name' => 'عميل جديد', 'phone' => '779999999'])->assertForbidden();
        $this->actingAs($user)->get("/admin/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($user)->get("/admin/customers/{$customer->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/admin/customers/{$customer->id}", ['name' => 'تعديل', 'phone' => '771234567'])->assertForbidden();
        $this->actingAs($user)->patch("/admin/customers/{$customer->id}/activate")->assertForbidden();
        $this->actingAs($user)->patch("/admin/customers/{$customer->id}/deactivate")->assertForbidden();
    }

    public function test_authorized_user_can_browse_and_filter_customers(): void
    {
        $admin = $this->userWithRole('admin');

        Customer::create([
            'name' => 'محمد أحمد',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'admin',
        ]);

        Customer::create([
            'name' => 'علي صالح',
            'phone' => '732222222',
            'normalized_phone' => '+967732222222',
            'is_active' => false,
            'phone_verified_at' => null,
            'phone_verification_method' => null,
        ]);

        $response = $this->actingAs($admin)->get('/admin/customers');
        $response->assertOk();
        $response->assertSee('محمد أحمد');
        $response->assertSee('علي صالح');
        $response->assertSee('قائمة العملاء (2)');

        // Filter by search
        $searchResponse = $this->actingAs($admin)->get('/admin/customers?search=محمد');
        $searchResponse->assertOk();
        $searchResponse->assertSee('محمد أحمد');
        $searchResponse->assertDontSee('علي صالح');

        // Filter by verification
        $verifiedResponse = $this->actingAs($admin)->get('/admin/customers?verification=verified');
        $verifiedResponse->assertOk();
        $verifiedResponse->assertSee('محمد أحمد');
        $verifiedResponse->assertDontSee('علي صالح');
    }

    public function test_can_create_customer_and_forged_verification_fields_are_ignored(): void
    {
        $admin = $this->userWithRole('admin');

        $payload = [
            'name' => 'عبدالله خالد',
            'phone' => '773333333',
            'email' => 'abdullah@example.com',
            'notes' => 'عميل مميز',
            'is_active' => '1',
            // Attempt to forge verification fields via mass assignment:
            'phone_verified_at' => '2026-01-01 00:00:00',
            'phone_verification_method' => 'admin',
            'phone_verified_by' => 999,
        ];

        $response = $this->actingAs($admin)->post('/admin/customers', $payload);

        $customer = Customer::query()->where('phone', '773333333')->firstOrFail();
        $response->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertSame('+967773333333', $customer->normalized_phone);
        $this->assertTrue($customer->is_active);

        // Security check: forged verification fields MUST NOT be written
        $this->assertNull($customer->phone_verified_at);
        $this->assertNull($customer->phone_verification_method);
        $this->assertNull($customer->phone_verified_by);
    }

    public function test_duplicate_canonical_phone_is_rejected_on_create(): void
    {
        $admin = $this->userWithRole('admin');

        Customer::create([
            'name' => 'العميل الأول',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        // Attempt to create with different representation of the same phone (+967 77 123 4567)
        $response = $this->actingAs($admin)->post('/admin/customers', [
            'name' => 'العميل الثاني',
            'phone' => '+967 77 123 4567',
        ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_can_update_customer_details(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'اسم قديم',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($admin)->put("/admin/customers/{$customer->id}", [
            'name' => 'اسم محدث',
            'phone' => '771234567',
            'email' => 'new@example.com',
            'is_active' => '1',
        ]);

        $response->assertRedirect("/admin/customers/{$customer->id}");
        $customer->refresh();
        $this->assertSame('اسم محدث', $customer->name);
        $this->assertSame('new@example.com', $customer->email);
    }

    public function test_can_activate_and_deactivate_customer(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل نشط',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch("/admin/customers/{$customer->id}/deactivate")
            ->assertRedirect();
        $this->assertFalse($customer->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/customers/{$customer->id}/activate")
            ->assertRedirect();
        $this->assertTrue($customer->fresh()->is_active);
    }

    public function test_authorized_user_can_view_customer_details_with_addresses(): void
    {
        $admin = $this->userWithRole('admin');

        $country = \App\Models\Country::query()->firstOrCreate(
            ['code' => 'YE'],
            ['name_ar' => 'اليمن', 'name_en' => 'Yemen', 'phone_code' => '+967', 'is_active' => true]
        );
        $governorate = \App\Models\Governorate::query()->firstOrCreate(
            ['country_id' => $country->id, 'name_ar' => 'صنعاء'],
            ['name_en' => 'Sanaa', 'slug' => 'sanaa', 'is_active' => true]
        );

        $customer = Customer::create([
            'name' => 'عميل مع عناوين',
            'phone' => '773333333',
            'normalized_phone' => '+967773333333',
            'is_active' => true,
        ]);

        \App\Models\CustomerAddress::create([
            'customer_id' => $customer->id,
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
            'label' => 'المنزل',
            'contact_name' => 'محمد أحمد',
            'contact_phone' => '773333333',
            'street' => 'شارع حدة',
            'latitude' => 15.369445,
            'longitude' => 44.191006,
            'is_default' => true,
        ]);

        $response = $this->actingAs($admin)->get("/admin/customers/{$customer->id}");

        $response->assertOk();
        $response->assertSee('عميل مع عناوين');
        $response->assertSee('المنزل');
        $response->assertSee('محمد أحمد');
        $response->assertSee('اليمن');
        $response->assertSee('صنعاء');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
