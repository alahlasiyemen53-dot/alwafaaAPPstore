<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
    }

    public function test_login_uses_velzon_rtl_assets_and_accessible_form(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('assets/css/bootstrap-rtl.min.css')
            ->assertSee('assets/css/app-rtl.min.css')
            ->assertSee('data-theme-colors="default"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('إظهار كلمة المرور');
    }

    public function test_admin_layout_loads_velzon_interactions_and_sweetalert(): void
    {
        $this->actingAs($this->makeUser('interactive-ui@example.com', 'super_admin'))
            ->withSession(['success' => 'تم الحفظ بنجاح.'])
            ->get('/admin')
            ->assertOk()
            ->assertSee('assets/js/layout.js')
            ->assertSee('assets/libs/simplebar/simplebar.min.js')
            ->assertSee('assets/libs/sweetalert2/sweetalert2.min.css')
            ->assertSee('assets/libs/sweetalert2/sweetalert2.min.js')
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('id="topnav-hamburger-icon"', false)
            ->assertSee('id="back-to-top"', false);
    }

    public function test_web_errors_use_the_velzon_layout(): void
    {
        $this->get('/missing-page')->assertNotFound()
            ->assertSee('assets/css/app-rtl.min.css')
            ->assertSee('الصفحة غير موجودة')
            ->assertDontSee('Laravel');
    }

    public function test_super_admin_can_render_all_sprint_three_ui_areas(): void
    {
        $this->actingAs($this->makeUser('root-ui@example.com', 'super_admin'));

        foreach (['/admin', '/admin/profile', '/admin/password', '/admin/users', '/admin/roles', '/admin/permissions',
            '/admin/master-data/currencies', '/admin/master-data/exchange-rates', '/admin/master-data/units',
            '/admin/master-data/governorates', '/admin/master-data/cities', '/admin/master-data/districts',
            '/admin/master-data/brands', '/admin/master-data/categories', '/admin/master-data/warehouses',
            '/admin/master-data/settings'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('layout-wrapper');
        }
    }

    public function test_ui_hides_actions_the_user_cannot_perform(): void
    {
        $role = Role::query()->where('name', 'customer_support')->firstOrFail();
        $role->syncPermissions(Permission::query()->whereIn('name', ['admin.dashboard.view', 'admin.users.view'])->get());
        $user = $this->makeUser('readonly-ui@example.com', 'customer_support');

        $this->actingAs($user)->get('/admin/users')->assertOk()
            ->assertDontSee('مستخدم جديد')
            ->assertDontSee('البيانات الأساسية');
        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_read_only_settings_show_values_without_editable_controls(): void
    {
        $role = Role::query()->where('name', 'customer_support')->firstOrFail();
        $role->syncPermissions(Permission::query()->whereIn('name', ['admin.dashboard.view', 'admin.settings.view'])->get());

        $this->actingAs($this->makeUser('settings-reader@example.com', 'customer_support'))
            ->get('/admin/master-data/settings')
            ->assertOk()
            ->assertSee('العملة الافتراضية')
            ->assertSee('disabled', false)
            ->assertDontSee('حفظ الإعدادات');
    }

    public function test_super_admin_can_create_user_through_web_form(): void
    {
        $this->actingAs($this->makeUser('root-create@example.com', 'super_admin'))
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/users', [
                'name' => 'مدير جديد', 'email' => 'new-ui@example.com', 'phone' => null,
                'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123',
                'is_active' => '1', 'roles' => ['admin'],
            ])->assertRedirect(route('admin.users.web.index'));

        $this->assertDatabaseHas('users', ['email' => 'new-ui@example.com', 'is_active' => true]);
        $this->assertTrue(User::query()->where('email', 'new-ui@example.com')->firstOrFail()->hasRole('admin'));
    }

    public function test_role_permissions_can_be_updated_through_ui(): void
    {
        $admin = $this->makeUser('root-role-ui@example.com', 'super_admin');
        $role = Role::query()->where('name', 'customer_support')->firstOrFail();
        $supportUser = $this->makeUser('support-role-cache@example.com', 'customer_support');

        // Warm Laratrust caches so a broken sync (without flush) would leave them stale.
        $this->assertFalse($role->hasPermission('admin.users.view'));
        $this->assertFalse($supportUser->hasPermission('admin.users.view'));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put("/admin/roles/{$role->id}/permissions", [
                'permissions' => ['admin.dashboard.view', 'admin.users.view'],
            ])->assertRedirect()->assertSessionHas('success');

        $role = $role->fresh();
        $this->assertTrue($role->hasPermission('admin.dashboard.view'));
        $this->assertTrue($role->hasPermission('admin.users.view'));
        $this->assertTrue($supportUser->fresh()->hasPermission('admin.users.view'));
        $this->assertFalse($role->hasPermission('admin.roles.update'));
    }

    public function test_role_permissions_can_be_cleared_through_ui(): void
    {
        $admin = $this->makeUser('root-role-clear@example.com', 'super_admin');
        $role = Role::query()->where('name', 'customer_support')->firstOrFail();
        $role->syncPermissions(['admin.dashboard.view', 'admin.users.view']);
        $this->assertTrue($role->hasPermission('admin.users.view'));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put("/admin/roles/{$role->id}/permissions", [
                // omitted permissions[] simulates unchecking all boxes
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($role->fresh()->hasPermission('admin.users.view'));
        $this->assertFalse($role->fresh()->hasPermission('admin.dashboard.view'));
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => 'StrongPassword123', 'is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
