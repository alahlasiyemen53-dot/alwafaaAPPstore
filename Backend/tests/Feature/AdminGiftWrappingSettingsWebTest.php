<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\GiftWrappingService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminGiftWrappingSettingsWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $unauthorizedUser;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);

        $this->admin = User::factory()->create();
        $adminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->admin->addRole($adminRole);

        $this->unauthorizedUser = User::factory()->create();

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::create([
            'key' => 'default_currency_id',
            'value' => (string) $this->currency->id,
            'type' => 'integer',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.master-data.settings'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_cannot_view_settings_or_update(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.master-data.settings'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'gift_wrapping_enabled' => 1,
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_settings_page_with_gift_wrapping_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master-data.settings'))
            ->assertOk()
            ->assertSee('خدمة تغليف الهدايا (Gift Wrapping Service)')
            ->assertSee('خدمة مجانية (Free Service) V1')
            ->assertSee('تفعيل خدمة تغليف الهدايا')
            ->assertSee('السماح بإرفاق بطاقة رسالة الهدية');
    }

    public function test_admin_can_update_gift_wrapping_settings_and_logs_audit(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'gift_wrapping_enabled' => 1,
                    'gift_message_enabled' => 1,
                    'gift_message_max_length' => 350,
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'gift_wrapping_enabled',
            'value' => '1',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'gift_message_enabled',
            'value' => '1',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'gift_message_max_length',
            'value' => '350',
        ]);

        $service = app(GiftWrappingService::class);
        $this->assertTrue($service->isEnabled());
        $this->assertTrue($service->isMessageEnabled());
        $this->assertSame(350, $service->getMaxMessageLength());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.settings.updated',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_invalid_max_length_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'gift_wrapping_enabled' => 1,
                    'gift_message_max_length' => 5, // minimum is 10
                ],
            ]);

        $response->assertSessionHasErrors('settings.gift_message_max_length');
    }
}
