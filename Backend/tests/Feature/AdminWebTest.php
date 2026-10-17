<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);
    }

    public function test_root_serves_marketing_home(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('الوفاء استور', false)
            ->assertSee('AW', false);
    }

    public function test_admin_web_login_reads_dashboard_from_database(): void
    {
        $user = User::factory()->create(['email' => 'web-admin@example.com', 'password' => 'StrongPassword123']);
        $user->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $this->get('/admin/login')->assertOk()->assertSee('تسجيل الدخول');
        $this->post('/admin/login', ['identifier' => $user->email, 'password' => 'StrongPassword123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk()->assertSee('ملفك الإداري')->assertSee($user->name);
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
    }

    public function test_guests_are_sent_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
