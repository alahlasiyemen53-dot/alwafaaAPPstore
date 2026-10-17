<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeding_creates_requested_super_admin_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'alahlasiyemen53@gmail.com')->firstOrFail();

        self::assertTrue($admin->is_active);
        self::assertTrue($admin->hasRole('super_admin'));
        self::assertTrue($admin->hasPermission('admin.users.view'));

        $this->post('/admin/login', [
            'identifier' => 'alahlasiyemen53@gmail.com',
            'password' => 'admin@123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
