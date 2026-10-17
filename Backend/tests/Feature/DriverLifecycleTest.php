<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');
    }

    public function test_can_create_driver_with_auto_generated_code(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/drivers', [
            'name' => 'محمد أحمد الصالحي',
            'phone' => '771234567',
            'email' => 'driver1@example.com',
            'vehicle_type' => 'دراجة نارية',
            'vehicle_plate_number' => 'ص 12345',
            'license_number' => 'LIC-9988',
            'is_active' => '1',
            'notes' => 'سائق ممتاز في أمانة العاصمة',
        ]);

        $response->assertRedirect('/admin/drivers');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('drivers', [
            'code' => 'DRV-0001',
            'name' => 'محمد أحمد الصالحي',
            'phone' => '771234567',
            'vehicle_type' => 'دراجة نارية',
            'is_active' => true,
        ]);
    }

    public function test_driver_phone_must_be_unique(): void
    {
        Driver::create([
            'driver_code' => 'DRV-0001',
            'name' => 'مندوب أول',
            'phone' => '771111111',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/drivers', [
            'name' => 'مندوب ثاني',
            'phone' => '771111111',
        ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertDatabaseCount('drivers', 1);
    }

    public function test_can_update_driver_and_retain_uniqueness(): void
    {
        $driver = Driver::create([
            'driver_code' => 'DRV-0001',
            'name' => 'مندوب قديم',
            'phone' => '771111111',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put("/admin/drivers/{$driver->id}", [
            'name' => 'مندوب معدل',
            'phone' => '771111111', // same phone
            'email' => 'updated@example.com',
            'vehicle_type' => 'سيارة صالون',
            'vehicle_plate_number' => 'أ 4567',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/drivers');
        $response->assertSessionHas('success');

        $driver->refresh();
        $this->assertSame('مندوب معدل', $driver->name);
        $this->assertSame('updated@example.com', $driver->email);
        $this->assertSame('سيارة صالون', $driver->vehicle_type);
    }

    public function test_can_toggle_driver_status(): void
    {
        $driver = Driver::create([
            'driver_code' => 'DRV-0001',
            'name' => 'مندوب تحت الاختبار',
            'phone' => '773333333',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/drivers/{$driver->id}/toggle-status");
        $response->assertSessionHas('success');

        $this->assertFalse($driver->fresh()->is_active);

        // Re-activate
        $this->actingAs($this->admin)->patch("/admin/drivers/{$driver->id}/toggle-status");
        $this->assertTrue($driver->fresh()->is_active);
    }

    public function test_drivers_index_filtering_and_metrics(): void
    {
        Driver::create(['driver_code' => 'DRV-0001', 'name' => 'علي صالح', 'phone' => '770000001', 'is_active' => true]);
        Driver::create(['driver_code' => 'DRV-0002', 'name' => 'أحمد هلال', 'phone' => '770000002', 'is_active' => false]);

        $response = $this->actingAs($this->admin)->get('/admin/drivers?search=علي');
        $response->assertOk();
        $response->assertSee('علي صالح');
        $response->assertDontSee('أحمد هلال');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
