<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAttributeWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
    }

    public function test_unauthorized_user_cannot_access_attributes(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        $this->get(route('admin.attributes.index'))->assertForbidden();
        $this->get(route('admin.attributes.create'))->assertForbidden();
    }

    public function test_authorized_admin_can_manage_attributes(): void
    {
        $this->actingAs($this->admin);

        // 1. View Index
        $this->get(route('admin.attributes.index'))->assertOk()->assertSee('خصائص المنتجات');

        // 2. Create Attribute
        $response = $this->post(route('admin.attributes.store'), [
            'name_ar' => 'الحجم',
            'name_en' => 'Size',
            'code' => 'size',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $attribute = Attribute::query()->where('code', 'size')->firstOrFail();
        $response->assertRedirect(route('admin.attributes.values.index', $attribute));
        $this->assertDatabaseHas('attributes', ['code' => 'size', 'name_ar' => 'الحجم', 'is_active' => true]);

        // 3. Update Attribute
        $this->put(route('admin.attributes.update', $attribute), [
            'name_ar' => 'الحجم والكمية',
            'name_en' => 'Size & Volume',
            'code' => 'size',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.attributes.index'));

        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name_ar' => 'الحجم والكمية']);

        // 4. Deactivate Attribute
        $this->patch(route('admin.attributes.deactivate', $attribute))->assertRedirect();
        $this->assertFalse($attribute->fresh()->is_active);

        // 5. Activate Attribute
        $this->patch(route('admin.attributes.activate', $attribute))->assertRedirect();
        $this->assertTrue($attribute->fresh()->is_active);
    }

    public function test_duplicate_attribute_code_is_rejected(): void
    {
        $this->actingAs($this->admin);

        Attribute::query()->create([
            'name_ar' => 'اللون',
            'code' => 'color',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->post(route('admin.attributes.store'), [
            'name_ar' => 'لون آخر',
            'code' => 'color',
        ])->assertSessionHasErrors('code');
    }

    public function test_authorized_admin_can_manage_attribute_values(): void
    {
        $this->actingAs($this->admin);

        $attribute = Attribute::query()->create([
            'name_ar' => 'اللون',
            'code' => 'color',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // 1. View Values Page
        $this->get(route('admin.attributes.values.index', $attribute))
            ->assertOk()
            ->assertSee('اللون');

        // 2. Add Value
        $this->post(route('admin.attributes.values.store', $attribute), [
            'value_ar' => 'أحمر',
            'value_en' => 'Red',
            'code' => 'red',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $value = AttributeValue::query()->where('attribute_id', $attribute->id)->where('code', 'red')->firstOrFail();
        $this->assertSame('أحمر', $value->value_ar);

        // 3. Update Value
        $this->put(route('admin.attributes.values.update', [$attribute, $value]), [
            'value_ar' => 'أحمر داكن',
            'value_en' => 'Dark Red',
            'code' => 'red',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('أحمر داكن', $value->fresh()->value_ar);

        // 4. Deactivate and Activate Value
        $this->patch(route('admin.attributes.values.deactivate', [$attribute, $value]))->assertRedirect();
        $this->assertFalse($value->fresh()->is_active);

        $this->patch(route('admin.attributes.values.activate', [$attribute, $value]))->assertRedirect();
        $this->assertTrue($value->fresh()->is_active);
    }
}
