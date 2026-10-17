<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->admin = User::factory()->create([
            'email' => 'session-admin@example.com',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => true,
        ]);
        $this->admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
    }

    public function test_login_regenerates_session_id(): void
    {
        $this->get(route('admin.login'))->assertOk();
        $before = session()->getId();

        $this->post(route('admin.authenticate'), [
            'identifier' => 'session-admin@example.com',
            'password' => 'SecretPass123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($before, session()->getId());
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull(session(config('admin_session.session_started_key')));
    }

    public function test_remember_me_is_disabled_by_default(): void
    {
        $response = $this->post(route('admin.authenticate'), [
            'identifier' => 'session-admin@example.com',
            'password' => 'SecretPass123!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    public function test_inactive_admin_is_logged_out_on_request(): void
    {
        $this->actingAs($this->admin);
        $this->admin->update(['is_active' => false]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_absolute_lifetime_forces_relogin(): void
    {
        config(['admin_session.absolute_lifetime_minutes' => 1]);

        $this->actingAs($this->admin);
        session([config('admin_session.session_started_key') => now()->subMinutes(5)->getTimestamp()]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_admin_can_list_and_revoke_other_sessions(): void
    {
        $this->actingAs($this->admin);

        DB::table('sessions')->insert([
            'id' => 'other-session-id-001',
            'user_id' => $this->admin->id,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.security.sessions.index'))
            ->assertOk()
            ->assertSee('الأمان والجلسات')
            ->assertSee('الجلسة الحالية');

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('admin.security.sessions.destroy', 'other-session-id-001'))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id-001']);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_cannot_revoke_another_users_session(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        DB::table('sessions')->insert([
            'id' => 'foreign-session-id-001',
            'user_id' => $other->id,
            'ip_address' => '9.9.9.9',
            'user_agent' => 'Mozilla/5.0',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('admin.security.sessions.destroy', 'foreign-session-id-001'))
            ->assertRedirect();

        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session-id-001']);
    }

    public function test_password_change_revokes_other_sessions(): void
    {
        $this->actingAs($this->admin);
        $currentId = session()->getId();

        DB::table('sessions')->insert([
            'id' => 'stale-session-id-001',
            'user_id' => $this->admin->id,
            'ip_address' => '1.1.1.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.password.update.web'), [
                'current_password' => 'SecretPass123!',
                'password' => 'NewSecretPass123!',
                'password_confirmation' => 'NewSecretPass123!',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'stale-session-id-001']);
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotSame($currentId, session()->getId());
    }

    public function test_sensitive_action_requires_password_confirmation(): void
    {
        $this->actingAs($this->admin);

        $this->delete(route('admin.security.sessions.destroy-others'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->admin);
        $sessionId = session()->getId();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertNotSame($sessionId, session()->getId());
    }
}
