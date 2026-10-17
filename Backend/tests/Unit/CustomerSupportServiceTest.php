<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class CustomerSupportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerSupportService();
        $this->service->clearCache();
    }

    public function test_service_defaults_when_empty(): void
    {
        $settings = $this->service->getSettings();

        $this->assertFalse($settings['phone_enabled']);
        $this->assertNull($settings['phone']);
        $this->assertNull($settings['normalized_phone']);
        $this->assertNull($settings['tel_link']);
        $this->assertFalse($settings['whatsapp_enabled']);
        $this->assertNull($settings['whatsapp_number']);
        $this->assertNull($settings['normalized_whatsapp_digits']);
        $this->assertNull($settings['whatsapp_default_message']);
        $this->assertNull($settings['whatsapp_deep_link']);
        $this->assertNull($settings['hours_text_ar']);

        $this->assertFalse($this->service->isPhoneEnabled());
        $this->assertNull($this->service->getPhoneTelLink());
        $this->assertFalse($this->service->isWhatsappEnabled());
        $this->assertNull($this->service->getWhatsappDeepLink());
        $this->assertNull($this->service->getHoursText());
    }

    public function test_service_generates_tel_link_for_yemeni_mobile(): void
    {
        Setting::create(['key' => 'support_phone_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_phone', 'value' => '770123456', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertTrue($this->service->isPhoneEnabled());
        $this->assertSame('+967770123456', $this->service->getNormalizedPhone());
        $this->assertSame('tel:+967770123456', $this->service->getPhoneTelLink());
    }

    public function test_service_generates_tel_link_for_yemeni_landline(): void
    {
        Setting::create(['key' => 'support_phone_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_phone', 'value' => '01234567', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertTrue($this->service->isPhoneEnabled());
        $this->assertSame('+9671234567', $this->service->getNormalizedPhone());
        $this->assertSame('tel:+9671234567', $this->service->getPhoneTelLink());
    }

    public function test_tel_link_is_null_when_phone_disabled(): void
    {
        Setting::create(['key' => 'support_phone_enabled', 'value' => '0', 'type' => 'boolean']);
        Setting::create(['key' => 'support_phone', 'value' => '770123456', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertFalse($this->service->isPhoneEnabled());
        $this->assertNull($this->service->getPhoneTelLink());
    }

    public function test_whatsapp_deep_link_generation_without_message(): void
    {
        Setting::create(['key' => 'support_whatsapp_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_whatsapp_number', 'value' => '+967 770 123 456', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertTrue($this->service->isWhatsappEnabled());
        $this->assertSame('967770123456', $this->service->getNormalizedWhatsappDigits());
        $this->assertSame('https://wa.me/967770123456', $this->service->getWhatsappDeepLink());
    }

    public function test_whatsapp_deep_link_generation_with_message(): void
    {
        Setting::create(['key' => 'support_whatsapp_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_whatsapp_number', 'value' => '770123456', 'type' => 'string']);
        Setting::create(['key' => 'support_whatsapp_default_message', 'value' => 'مرحباً، أود الاستفسار عن طلبي', 'type' => 'string']);
        $this->service->clearCache();

        $deepLink = $this->service->getWhatsappDeepLink();
        $this->assertNotNull($deepLink);
        $this->assertStringStartsWith('https://wa.me/967770123456?text=', $deepLink);
        $this->assertStringContainsString(rawurlencode('مرحباً، أود الاستفسار عن طلبي'), $deepLink);
    }

    public function test_whatsapp_deep_link_is_null_when_disabled(): void
    {
        Setting::create(['key' => 'support_whatsapp_enabled', 'value' => '0', 'type' => 'boolean']);
        Setting::create(['key' => 'support_whatsapp_number', 'value' => '770123456', 'type' => 'string']);
        Setting::create(['key' => 'support_whatsapp_default_message', 'value' => 'مرحباً', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertFalse($this->service->isWhatsappEnabled());
        $this->assertNull($this->service->getWhatsappDeepLink());
    }

    public function test_service_sanitizes_html_in_message_and_hours(): void
    {
        Setting::create(['key' => 'support_whatsapp_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_whatsapp_number', 'value' => '770123456', 'type' => 'string']);
        Setting::create(['key' => 'support_whatsapp_default_message', 'value' => '<b>مرحبا</b><script>alert(1)</script>', 'type' => 'string']);
        Setting::create(['key' => 'support_hours_text_ar', 'value' => '<p>السبت - الخميس: 9 ص - 10 م</p>', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertSame('مرحباalert(1)', $this->service->getWhatsappDefaultMessage());
        $this->assertSame('السبت - الخميس: 9 ص - 10 م', $this->service->getHoursText());
        $this->assertStringNotContainsString('<script>', (string) $this->service->getWhatsappDeepLink());
    }

    public function test_clear_cache_invalidates_cached_values(): void
    {
        Setting::create(['key' => 'support_phone_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'support_phone', 'value' => '770123456', 'type' => 'string']);
        $this->service->clearCache();

        $this->assertSame('tel:+967770123456', $this->service->getPhoneTelLink());

        Setting::where('key', 'support_phone')->update(['value' => '770999999']);
        // Without clearCache, still cached
        $this->assertSame('tel:+967770123456', $this->service->getPhoneTelLink());

        $this->service->clearCache();
        $this->assertSame('tel:+967770999999', $this->service->getPhoneTelLink());
    }
}
