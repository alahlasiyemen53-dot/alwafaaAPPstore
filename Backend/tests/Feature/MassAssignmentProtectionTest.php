<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MassAssignmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_cannot_smuggle_is_active_field(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'name' => 'Original Name']);
        $user->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $response = $this->actingAs($user)->put('/admin/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertTrue((bool) $user->is_active, 'is_active must not be updatable through the self-service profile form.');
    }

    public function test_api_profile_update_cannot_smuggle_is_active_field(): void
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create(['is_active' => true, 'name' => 'Original Name']);
        $user->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/v1/admin/profile', [
            'name' => 'Updated Via Api',
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => false,
        ]);

        $response->assertOk();
        $user->refresh();

        $this->assertSame('Updated Via Api', $user->name);
        $this->assertTrue((bool) $user->is_active, 'is_active must not be updatable through the self-service API profile endpoint.');
    }
}
