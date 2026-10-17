<?php

namespace Tests\Feature;

use App\Models\CustomerTier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerTierWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::create([
            'name' => "User {$role}",
            'email' => "{$role}_".uniqid().'@lamma.test',
            'password' => bcrypt('password'),
        ]);

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel);
        }

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/customer-tiers')->assertRedirect('/admin/login');
        $this->get('/admin/customer-tiers/create')->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_cannot_access_or_manage_customer_tiers(): void
    {
        $cashier = $this->userWithRole('cashier');
        $tier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $this->actingAs($cashier)->get('/admin/customer-tiers')->assertForbidden();
        $this->actingAs($cashier)->get('/admin/customer-tiers/create')->assertForbidden();
        $this->actingAs($cashier)->post('/admin/customer-tiers', ['code' => 'TEST', 'name_ar' => 'تجريبي'])->assertForbidden();
        $this->actingAs($cashier)->get("/admin/customer-tiers/{$tier->id}/edit")->assertForbidden();
        $this->actingAs($cashier)->put("/admin/customer-tiers/{$tier->id}", ['name_ar' => 'تعديل'])->assertForbidden();
        $this->actingAs($cashier)->delete("/admin/customer-tiers/{$tier->id}")->assertForbidden();
        $this->actingAs($cashier)->patch("/admin/customer-tiers/{$tier->id}/toggle-status")->assertForbidden();
    }

    public function test_authorized_admin_can_browse_and_filter_customer_tiers(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get('/admin/customer-tiers');
        $response->assertOk();
        $response->assertSee('المستوى الأساسي');
        $response->assertSee('كبار الشخصيات VIP');

        // Test filter
        $searchResponse = $this->actingAs($admin)->get('/admin/customer-tiers?search=VIP');
        $searchResponse->assertOk();
        $searchResponse->assertSee('كبار الشخصيات VIP');
    }

    public function test_authorized_admin_can_create_customer_tier(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post('/admin/customer-tiers', [
            'code' => 'BRONZE',
            'name_ar' => 'برونزي',
            'name_en' => 'Bronze',
            'description' => 'المستوى البرونزي للعملاء الجدد',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/customer-tiers');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_tiers', [
            'code' => 'BRONZE',
            'name_ar' => 'برونزي',
            'sort_order' => 5,
        ]);
    }

    public function test_authorized_admin_can_update_customer_tier(): void
    {
        $admin = $this->userWithRole('admin');
        $tier = CustomerTier::where('code', 'VIP')->firstOrFail();

        $response = $this->actingAs($admin)->put("/admin/customer-tiers/{$tier->id}", [
            'name_ar' => 'كبار العملاء VIP المميزين',
            'name_en' => 'VIP Members',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/customer-tiers');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_tiers', [
            'id' => $tier->id,
            'name_ar' => 'كبار العملاء VIP المميزين',
            'sort_order' => 20,
        ]);
    }

    public function test_admin_cannot_deactivate_default_tier(): void
    {
        $admin = $this->userWithRole('admin');
        $defaultTier = CustomerTier::where('is_default', true)->firstOrFail();

        $response = $this->actingAs($admin)->patch("/admin/customer-tiers/{$defaultTier->id}/toggle-status");
        $response->assertSessionHasErrors(['is_active']);

        $this->assertTrue($defaultTier->fresh()->is_active);
    }

    public function test_admin_can_delete_unused_tier(): void
    {
        $admin = $this->userWithRole('admin');
        $tier = CustomerTier::create([
            'code' => 'DISCARDABLE',
            'name_ar' => 'مستوى مؤقت للحذف',
            'is_active' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/customer-tiers/{$tier->id}");
        $response->assertRedirect('/admin/customer-tiers');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('customer_tiers', [
            'id' => $tier->id,
        ]);
    }

    public function test_xss_protection_in_tier_rendering(): void
    {
        $admin = $this->userWithRole('admin');
        $xssName = 'مستوى <script>alert("XSS")</script>';

        CustomerTier::create([
            'code' => 'XSS_TEST',
            'name_ar' => $xssName,
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/customer-tiers');
        $response->assertOk();
        $response->assertDontSee('<script>alert("XSS")</script>', false);
        $response->assertSee(e($xssName), false);
    }
}
