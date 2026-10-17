<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerSupportService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerSupportSettingsWebTest extends TestCase
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

    public function test_unauthorized_user_cannot_view_or_update_support_settings(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.master-data.settings'))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_phone_enabled' => 1,
                    'support_phone' => '770123456',
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_support_settings_section(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master-data.settings'))
            ->assertOk()
            ->assertSee('خدمة وتواصل دعم العملاء (Customer Support Contact)')
            ->assertSee('تفعيل الاتصال الهاتفي')
            ->assertSee('تفعيل محادثات واتساب')
            ->assertSee('ساعات وأوقات العمل المعتمدة');
    }

    public function test_validation_fails_if_phone_enabled_without_valid_number(): void
    {
        // Empty phone
        $response = $this->actingAs($this->admin)
            ->from(route('admin.master-data.settings'))
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_phone_enabled' => 1,
                    'support_phone' => '',
                ],
            ]);

        $response->assertSessionHasErrors(['settings.support_phone']);

        // Invalid phone number
        $responseInvalid = $this->actingAs($this->admin)
            ->from(route('admin.master-data.settings'))
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_phone_enabled' => 1,
                    'support_phone' => '12345',
                ],
            ]);

        $responseInvalid->assertSessionHasErrors(['settings.support_phone']);
    }

    public function test_validation_fails_if_whatsapp_enabled_without_valid_number(): void
    {
        // Empty WhatsApp
        $response = $this->actingAs($this->admin)
            ->from(route('admin.master-data.settings'))
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_whatsapp_enabled' => 1,
                    'support_whatsapp_number' => '',
                ],
            ]);

        $response->assertSessionHasErrors(['settings.support_whatsapp_number']);

        // Invalid WhatsApp number
        $responseInvalid = $this->actingAs($this->admin)
            ->from(route('admin.master-data.settings'))
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_whatsapp_enabled' => 1,
                    'support_whatsapp_number' => 'abcde',
                ],
            ]);

        $responseInvalid->assertSessionHasErrors(['settings.support_whatsapp_number']);
    }

    public function test_admin_can_update_support_settings_and_logs_audit(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.master-data.settings.update'), [
                'settings' => [
                    'default_currency_id' => $this->currency->id,
                    'support_phone_enabled' => 1,
                    'support_phone' => '770123456',
                    'support_whatsapp_enabled' => 1,
                    'support_whatsapp_number' => '771234567',
                    'support_whatsapp_default_message' => 'مرحباً، أود الاستفسار عن طلبي',
                    'support_hours_text_ar' => 'يومياً من 9 صباحاً حتى 11 مساءً',
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'support_phone_enabled',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'support_phone',
            'value' => '770123456',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'support_whatsapp_enabled',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'support_whatsapp_number',
            'value' => '771234567',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'support_hours_text_ar',
            'value' => 'يومياً من 9 صباحاً حتى 11 مساءً',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.settings.updated',
            'actor_id' => $this->admin->id,
        ]);

        // Verify that viewing settings now renders the generated deep links
        $viewResponse = $this->actingAs($this->admin)
            ->get(route('admin.master-data.settings'));

        $viewResponse->assertOk()
            ->assertSee('tel:+967770123456')
            ->assertSee('https://wa.me/967771234567');
    }
}
