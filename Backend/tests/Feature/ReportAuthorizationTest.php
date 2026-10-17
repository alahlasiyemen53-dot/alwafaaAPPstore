<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
    }

    private function makeUser(): User
    {
        static $i = 0;
        $i++;

        return User::create([
            'name' => "Report Tester {$i}",
            'email' => "report_tester_{$i}@lamma.test",
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login_on_report_routes(): void
    {
        $this->get(route('admin.reports.sales'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reports.inventory'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reports.customers'))->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permission_receives_403(): void
    {
        $user = $this->makeUser();
        $user->syncRoles(['customer_support']);

        $this->actingAs($user)->get(route('admin.reports.sales'))->assertForbidden();
    }

    public function test_operations_role_cannot_open_finance_or_wallet_reports(): void
    {
        $user = $this->makeUser();
        $user->syncRoles(['operations_manager']);

        $this->actingAs($user)->get(route('admin.finance.reports.integrity'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.reports.customer-wallets'))->assertForbidden();
    }

    public function test_sales_report_requires_export_permission_separately(): void
    {
        $roleWithViewOnly = Role::firstOrCreate(['name' => 'sales_view_only'], ['display_name' => 'Sales View Only']);
        $roleWithViewOnly->syncPermissions(
            Permission::whereIn('name', ['admin.dashboard.view', 'admin.reports.sales.view'])->get()
        );

        $user = $this->makeUser();
        $user->syncRoles(['sales_view_only']);

        $this->actingAs($user)->get(route('admin.reports.sales'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.sales.export'))->assertForbidden();
    }

    public function test_super_admin_can_view_every_report(): void
    {
        $user = $this->makeUser();
        $user->syncRoles(['super_admin']);

        $this->actingAs($user)->get(route('admin.reports.sales'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.purchasing'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.inventory'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.returns'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.marketing'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.delivery'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.driver-cod'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.customers'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.loyalty'))->assertOk();
        $this->actingAs($user)->get(route('admin.reports.customer-wallets'))->assertOk();
        $this->actingAs($user)->get(route('admin.finance.reports.integrity'))->assertOk();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }
}
