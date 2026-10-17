<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_admin_can_login_and_access_profile(): void
    {
        $user = $this->makeUser('admin@example.com', 'admin');

        $login = $this->postJson('/api/v1/admin/login', ['identifier' => $user->email, 'password' => 'StrongPassword123']);
        $token = $login->json('data.token');

        $login->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->withToken($token)->getJson('/api/v1/admin/profile')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_and_inactive_admins_are_rejected_without_account_enumeration(): void
    {
        $user = $this->makeUser('inactive@example.com', 'admin', false);

        $this->postJson('/api/v1/admin/login', ['identifier' => $user->email, 'password' => 'StrongPassword123'])
            ->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');

        $this->postJson('/api/v1/admin/login', ['identifier' => 'missing@example.com', 'password' => 'StrongPassword123'])
            ->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->makeUser('logout@example.com', 'admin');
        $token = $user->createToken('test')->plainTextToken;

        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/v1/admin/logout', [], $headers)->assertOk();
        Auth::forgetGuards();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->getJson('/api/v1/admin/profile', $headers)->assertUnauthorized();
    }

    public function test_authorized_admin_can_create_user_and_unauthorized_user_cannot_list(): void
    {
        $admin = $this->makeUser('manager@example.com', 'admin');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/users', [
            'name' => 'New Admin', 'email' => 'new@example.com', 'password' => 'NewStrongPassword123',
            'password_confirmation' => 'NewStrongPassword123', 'roles' => ['customer_support'],
        ])->assertCreated();

        $limited = $this->makeUser('support@example.com', 'customer_support');
        $limitedToken = $limited->createToken('test')->plainTextToken;
        $this->assertFalse($limited->fresh()->hasPermission('admin.users.view'));
        Auth::forgetGuards();
        $this->getJson('/api/v1/admin/users', ['Authorization' => 'Bearer '.$limitedToken])->assertForbidden();
    }

    public function test_normal_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->makeUser('normal@example.com', 'admin');
        $target = $this->makeUser('target@example.com', 'customer_support');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['roles' => ['super_admin']])->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('super_admin'));
    }

    public function test_final_active_super_admin_cannot_be_deactivated_or_demoted(): void
    {
        $actor = $this->makeUser('root1@example.com', 'super_admin');
        $target = $this->makeUser('root2@example.com', 'super_admin');
        $token = $actor->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/admin/users/'.$target->id.'/deactivate')->assertOk();
        $this->withToken($token)->patchJson('/api/v1/admin/users/'.$target->id.'/deactivate')->assertStatus(409);
        $this->withToken($token)->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['roles' => ['admin']])->assertStatus(409);
    }

    public function test_user_can_change_password_and_protected_fields_are_not_profile_assignable(): void
    {
        $user = $this->makeUser('password@example.com', 'admin');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/admin/profile', ['name' => 'Changed', 'is_active' => false, 'password' => 'bad'])
            ->assertOk();
        $this->assertTrue($user->fresh()->is_active);

        $this->withToken($token)->putJson('/api/v1/admin/password', [
            'current_password' => 'StrongPassword123', 'password' => 'AnotherStrongPassword123', 'password_confirmation' => 'AnotherStrongPassword123',
        ])->assertOk();
        $this->assertTrue(Hash::check('AnotherStrongPassword123', $user->fresh()->password));
    }

    public function test_super_admin_can_manage_roles_and_permissions(): void
    {
        $admin = $this->makeUser('root@example.com', 'super_admin');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/roles')->assertOk();
        $role = $this->withToken($token)->postJson('/api/v1/admin/roles', ['name' => 'auditor', 'display_name' => 'Auditor'])->assertCreated()->json('data.id');
        $this->withToken($token)->putJson('/api/v1/admin/roles/'.$role.'/permissions', ['permissions' => ['admin.users.view']])->assertOk()->assertJsonPath('data.permissions.0.name', 'admin.users.view');
        $this->withToken($token)->getJson('/api/v1/admin/permissions')->assertOk();
    }

    private function makeUser(string $email, string $role, bool $active = true): User
    {
        $user = User::factory()->create(['email' => $email, 'password' => 'StrongPassword123', 'is_active' => $active]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
