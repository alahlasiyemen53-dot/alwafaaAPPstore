<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\GiftWrappingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class GiftWrappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GiftWrappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GiftWrappingService();
        $this->service->clearCache();
    }

    public function test_service_defaults_and_free_invariant(): void
    {
        $this->assertFalse($this->service->isEnabled());
        $this->assertFalse($this->service->isMessageEnabled());
        $this->assertSame(250, $this->service->getMaxMessageLength());
        $this->assertTrue($this->service->isFree());
    }

    public function test_service_settings_when_enabled(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_max_length', 'value' => '300', 'type' => 'integer']);
        $this->service->clearCache();

        $this->assertTrue($this->service->isEnabled());
        $this->assertTrue($this->service->isMessageEnabled());
        $this->assertSame(300, $this->service->getMaxMessageLength());
    }

    public function test_message_disabled_if_wrapping_is_disabled(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '0', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '1', 'type' => 'boolean']);
        $this->service->clearCache();

        $this->assertFalse($this->service->isEnabled());
        $this->assertFalse($this->service->isMessageEnabled());
    }

    public function test_max_message_length_bounds(): void
    {
        Setting::create(['key' => 'gift_message_max_length', 'value' => '5', 'type' => 'integer']);
        $this->service->clearCache();
        $this->assertSame(GiftWrappingService::MIN_MESSAGE_LENGTH, $this->service->getMaxMessageLength());

        Setting::updateOrCreate(['key' => 'gift_message_max_length'], ['value' => '5000', 'type' => 'integer']);
        $this->service->clearCache();
        $this->assertSame(GiftWrappingService::MAX_ALLOWED_MESSAGE_LENGTH, $this->service->getMaxMessageLength());
    }

    public function test_validate_and_clean_message_sanitizes_html(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '1', 'type' => 'boolean']);
        $this->service->clearCache();

        $dirtyMessage = "<script>alert('xss');</script><b>تهانينا الحارة!</b>";
        $cleaned = $this->service->validateAndCleanMessage($dirtyMessage, wrappingRequested: true);

        $this->assertSame("alert('xss');تهانينا الحارة!", $cleaned);
    }

    public function test_validate_and_clean_message_returns_null_when_not_requested(): void
    {
        $result = $this->service->validateAndCleanMessage('رسالة هدية', wrappingRequested: false);
        $this->assertNull($result);
    }

    public function test_validate_and_clean_message_rejects_when_service_disabled(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '0', 'type' => 'boolean']);
        $this->service->clearCache();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('خدمة تغليف الهدايا غير مفعّلة حالياً.');

        $this->service->validateAndCleanMessage('رسالة هدية', wrappingRequested: true);
    }

    public function test_validate_and_clean_message_rejects_when_message_disabled(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '0', 'type' => 'boolean']);
        $this->service->clearCache();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('خدمة إرفاق بطاقة رسالة الهدية غير مفعّلة حالياً.');

        $this->service->validateAndCleanMessage('رسالة هدية', wrappingRequested: true);
    }

    public function test_validate_and_clean_message_rejects_oversized_message(): void
    {
        Setting::create(['key' => 'gift_wrapping_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_enabled', 'value' => '1', 'type' => 'boolean']);
        Setting::create(['key' => 'gift_message_max_length', 'value' => '20', 'type' => 'integer']);
        $this->service->clearCache();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('يتجاوز الحد المسموح به');

        $this->service->validateAndCleanMessage('هذه رسالة هدية طويلة جداً تتجاوز الحد المسموح به قطعاً', wrappingRequested: true);
    }
}
