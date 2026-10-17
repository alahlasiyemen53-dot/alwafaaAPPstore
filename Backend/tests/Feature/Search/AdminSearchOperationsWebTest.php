<?php

namespace Tests\Feature\Search;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSearchOperationsWebTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $viewer;

    private User $manager;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->addRole(Role::where('name', 'super_admin')->firstOrFail());

        $this->viewer = User::factory()->create();
        $viewRole = Role::create(['name' => 'search_viewer', 'display_name' => 'Search Viewer']);
        $viewRole->syncPermissions(Permission::where('name', 'admin.search.view')->get());
        $this->viewer->addRole($viewRole);

        $this->manager = User::factory()->create();
        $managerRole = Role::create(['name' => 'search_manager', 'display_name' => 'Search Manager']);
        $managerRole->syncPermissions(Permission::whereIn('name', ['admin.search.view', 'admin.search.manage'])->get());
        $this->manager->addRole($managerRole);

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.search.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.search.index'));
        $response->assertForbidden();
    }

    public function test_viewer_can_see_search_dashboard_and_query(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('admin.search.index', ['q' => 'samsung']));
        $response->assertOk();
        $response->assertSee('عمليات ومحرك البحث');
        $response->assertSee('محرك البحث النصي');
        $response->assertSee('المتجهات البصرية المفهرسة');
    }

    public function test_viewer_cannot_trigger_reindex_without_manage_permission(): void
    {
        $response = $this->actingAs($this->viewer)->post(route('admin.search.reindex_text'));
        $response->assertForbidden();
    }

    public function test_manager_can_trigger_reindex_text(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.search.reindex_text'));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_manager_can_trigger_reconcile_text(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.search.reconcile_text'));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_manager_can_trigger_reindex_visual(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.search.reindex_visual'));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_manager_can_trigger_reconcile_visual(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.search.reconcile_visual'));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
