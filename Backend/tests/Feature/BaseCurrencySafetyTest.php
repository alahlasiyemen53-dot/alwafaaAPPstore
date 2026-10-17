<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BaseCurrencySafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Currency $currencyYer;

    private Currency $currencySar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->admin = User::factory()->create();
        $adminRole = Role::where('name', 'super_admin')->firstOrFail();
        $this->admin->addRole($adminRole);

        $this->currencyYer = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->currencySar = Currency::create([
            'code' => 'SAR',
            'name_ar' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_default' => false,
            'is_active' => true,
        ]);

        Setting::create([
            'key' => 'default_currency_id',
            'value' => (string) $this->currencyYer->id,
            'type' => 'integer',
        ]);
    }

    public function test_settings_page_displays_current_base_currency(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master-data.settings'));
        $response->assertOk();
        $response->assertSee('ريال يمني');
        $response->assertSee('العملة الأساسية للنظام (Base Currency)');
        $response->assertSee('المصدر الوحيد المعتمد');
    }

    public function test_can_change_base_currency_when_no_financial_transactions_exist(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.master-data.settings.update'), [
            'settings' => [
                'default_currency_id' => $this->currencySar->id,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(
            (string) $this->currencySar->id,
            Setting::where('key', 'default_currency_id')->value('value')
        );

        $this->assertTrue($this->currencySar->fresh()->is_default);
        $this->assertFalse($this->currencyYer->fresh()->is_default);
    }

    public function test_cannot_change_base_currency_when_orders_exist(): void
    {
        $governorate = Governorate::create([
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'code' => 'WH-TEST',
            'name_ar' => 'مستودع رئيسي',
            'governorate_id' => $governorate->id,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل اختبار',
            'phone' => '777000111',
            'normalized_phone' => '777000111',
            'is_active' => true,
        ]);

        // Create an existing order
        Order::create([
            'order_number' => 'ORD-SAFETY-001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $this->currencyYer->id,
            'status' => OrderStatus::Draft,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'subtotal' => 1000.0000,
            'discount_amount' => 0.0000,
            'delivery_fee' => 1000.0000,
            'base_currency_delivery_fee' => 1000.0000,
            'total' => 2000.0000,
            'exchange_rate_snapshot' => '1.00000000',
            'base_currency_total' => 2000.0000,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Attempt to change base currency from YER to SAR
        $response = $this->actingAs($this->admin)->put(route('admin.master-data.settings.update'), [
            'settings' => [
                'default_currency_id' => $this->currencySar->id,
            ],
        ]);

        $response->assertSessionHasErrors(['settings.default_currency_id']);
        $this->assertEquals(
            (string) $this->currencyYer->id,
            Setting::where('key', 'default_currency_id')->value('value')
        );
    }

    public function test_cannot_deactivate_the_current_base_currency(): void
    {
        $response = $this->actingAs($this->admin)->from(route('admin.master-data.currencies.index'))->patch(
            route('admin.master-data.currencies.deactivate', $this->currencyYer)
        );

        // Web UI soft-fails with flash error (API still surfaces ConflictHttpException as 409).
        $response->assertRedirect(route('admin.master-data.currencies.index'));
        $response->assertSessionHas('error');
        $this->assertTrue((bool) $this->currencyYer->fresh()->is_active);
    }

    public function test_can_store_exchange_rate_with_various_date_formats(): void
    {
        // 1. Standard format
        $response1 = $this->actingAs($this->admin)->post(route('admin.master-data.exchange-rates.store'), [
            'target_currency_id' => $this->currencySar->id,
            'rate' => 140.5,
            'effective_at' => '2026-09-16 10:30',
        ]);
        $response1->assertRedirect(route('admin.master-data.exchange-rates'));
        $this->assertDatabaseHas('exchange_rates', [
            'from_currency_id' => $this->currencySar->id,
            'to_currency_id' => $this->currencyYer->id,
            'rate' => '140.50000000',
        ]);

        // 2. Duplicated time concatenation from client-side flatpickr bug
        $response2 = $this->actingAs($this->admin)->post(route('admin.master-data.exchange-rates.store'), [
            'target_currency_id' => $this->currencySar->id,
            'rate' => 141.0,
            'effective_at' => '2026-09-16 14:00 14:00',
        ]);
        $response2->assertRedirect(route('admin.master-data.exchange-rates'));
        $this->assertDatabaseHas('exchange_rates', [
            'from_currency_id' => $this->currencySar->id,
            'rate' => '141.00000000',
        ]);

        // 3. Eastern Arabic numerals
        $response3 = $this->actingAs($this->admin)->post(route('admin.master-data.exchange-rates.store'), [
            'target_currency_id' => $this->currencySar->id,
            'rate' => 142.0,
            'effective_at' => '٢٠٢٦-٠٩-١٦ ١٥:٣٠',
        ]);
        $response3->assertRedirect(route('admin.master-data.exchange-rates'));
        $this->assertDatabaseHas('exchange_rates', [
            'from_currency_id' => $this->currencySar->id,
            'rate' => '142.00000000',
        ]);
    }

    public function test_invalid_date_returns_arabic_error_message(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.master-data.exchange-rates.store'), [
            'target_currency_id' => $this->currencySar->id,
            'rate' => 140.0,
            'effective_at' => 'invalid-date-string',
        ]);

        $response->assertSessionHasErrors([
            'effective_at' => 'صيغة تاريخ ووقت السريان غير صالحة. يرجى إدخال تاريخ صالح (مثل: 2026-09-16 12:00).',
        ]);
    }
}
