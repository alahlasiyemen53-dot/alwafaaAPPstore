<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DeliveryMethodSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminGovernorateDeliveryMethodWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class, DeliveryMethodSeeder::class]);
    }

    public function test_authorized_admin_can_manage_governorate_delivery_assignments(): void
    {
        $admin = User::factory()->create(['email' => 'gov-dm-web@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $governorate = Governorate::query()->firstOrFail();
        $method = DeliveryMethod::query()->where('code', 'express')->firstOrFail();

        $this->get('/admin/master-data/governorate-delivery-methods')
            ->assertOk()
            ->assertSee('ربط طرق التوصيل بالمحافظات');

        GovernorateDeliveryMethod::query()
            ->where('governorate_id', $governorate->id)
            ->where('delivery_method_id', $method->id)
            ->delete();

        $this->post('/admin/master-data/governorate-delivery-methods', [
            'governorate_id' => $governorate->id,
            'delivery_method_id' => $method->id,
            'delivery_fee' => 2750,
            'sort_order' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('admin.master-data.governorate-delivery-methods.index'));

        $assignment = GovernorateDeliveryMethod::query()
            ->where('governorate_id', $governorate->id)
            ->where('delivery_method_id', $method->id)
            ->firstOrFail();

        $this->assertSame(2750.0, (float) $assignment->delivery_fee);

        $this->put("/admin/master-data/governorate-delivery-methods/{$assignment->id}", [
            'governorate_id' => $governorate->id,
            'delivery_method_id' => $method->id,
            'delivery_fee' => 3100,
            'sort_order' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('admin.master-data.governorate-delivery-methods.index'));

        $this->assertSame(3100.0, (float) $assignment->fresh()->delivery_fee);

        $this->patch("/admin/master-data/governorate-delivery-methods/{$assignment->id}/deactivate")
            ->assertRedirect();
        $this->assertFalse($assignment->fresh()->is_active);
    }

    public function test_delivery_method_manage_page_and_locked_create_redirect(): void
    {
        $admin = User::factory()->create(['email' => 'gov-dm-manage@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $method = DeliveryMethod::query()->where('code', 'standard')->firstOrFail();
        $governorate = Governorate::query()->orderByDesc('id')->firstOrFail();

        $this->get(route('admin.master-data.delivery-methods.manage', $method))
            ->assertOk()
            ->assertSee('المحافظات والتسعير')
            ->assertSee($method->name_ar);

        GovernorateDeliveryMethod::query()
            ->where('governorate_id', $governorate->id)
            ->where('delivery_method_id', $method->id)
            ->delete();

        $this->post('/admin/master-data/governorate-delivery-methods', [
            'governorate_id' => $governorate->id,
            'delivery_method_id' => $method->id,
            'delivery_fee' => 1800,
            'sort_order' => 1,
            'is_active' => 1,
            'return_to_method' => 1,
            'from_method_manage' => 1,
        ])->assertRedirect(route('admin.master-data.delivery-methods.manage', $method));
    }

    public function test_duplicate_governorate_method_assignment_is_rejected(): void
    {
        $admin = User::factory()->create(['email' => 'gov-dm-dup@example.com']);
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);

        $existing = GovernorateDeliveryMethod::query()->firstOrFail();

        $this->from(route('admin.master-data.governorate-delivery-methods.create'))
            ->post('/admin/master-data/governorate-delivery-methods', [
                'governorate_id' => $existing->governorate_id,
                'delivery_method_id' => $existing->delivery_method_id,
                'delivery_fee' => 100,
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.master-data.governorate-delivery-methods.create'))
            ->assertSessionHasErrors('delivery_method_id');
    }
}
