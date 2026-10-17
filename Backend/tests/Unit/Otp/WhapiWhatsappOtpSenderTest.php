<?php

declare(strict_types=1);

namespace Tests\Unit\Otp;

use App\Contracts\OtpSenderInterface;
use App\Enums\OtpPurpose;
use App\Services\Otp\WhapiWhatsappOtpSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WhapiWhatsappOtpSenderTest extends TestCase
{
    public function test_sends_otp_message_via_whapi_successfully(): void
    {
        Http::fake([
            'https://whapi.alwafaaye.com/api/send' => Http::response([
                'ok' => true,
                'status' => 'queued',
                'messageId' => 'msg_whapi_123456',
                'userId' => 'usr_789',
            ], 200),
        ]);

        $sender = new WhapiWhatsappOtpSender(
            url: 'https://whapi.alwafaaye.com',
            apiKey: 'test-api-key-secret',
            timeout: 5,
        );

        $result = $sender->send('+967771234567', '458921', OtpPurpose::PhoneVerification);

        $this->assertTrue($result->successful);
        $this->assertSame('whapi', $result->provider);
        $this->assertSame('msg_whapi_123456', $result->messageId);
        $this->assertNull($result->error);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://whapi.alwafaaye.com/api/send'
                && $request->hasHeader('x-api-key', 'test-api-key-secret')
                && $request['number'] === '967771234567'
                && str_contains($request['message'], '458921')
                && str_contains($request['message'], 'تأكيد حسابك');
        });
    }

    public function test_login_purpose_formats_login_message(): void
    {
        Http::fake([
            'https://whapi.alwafaaye.com/api/send' => Http::response([
                'ok' => true,
                'status' => 'queued',
                'messageId' => 'msg_login_001',
            ], 200),
        ]);

        $sender = new WhapiWhatsappOtpSender(
            url: 'https://whapi.alwafaaye.com',
            apiKey: 'secret_key',
        );

        $result = $sender->send('96777999888', '112233', OtpPurpose::Login);

        $this->assertTrue($result->successful);
        $this->assertSame('msg_login_001', $result->messageId);

        Http::assertSent(function (Request $request): bool {
            return $request['number'] === '96777999888'
                && str_contains($request['message'], 'تسجيل الدخول')
                && str_contains($request['message'], '112233');
        });
    }

    public function test_fails_gracefully_when_api_key_is_missing(): void
    {
        Http::fake();

        $sender = new WhapiWhatsappOtpSender(
            url: 'https://whapi.alwafaaye.com',
            apiKey: '',
        );

        $result = $sender->send('+967771234567', '123456', OtpPurpose::PhoneVerification);

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('API_KEY', $result->error ?? '');
        Http::assertNothingSent();
    }

    public function test_fails_gracefully_when_whapi_returns_error_response(): void
    {
        Http::fake([
            'https://whapi.alwafaaye.com/api/send' => Http::response([
                'ok' => false,
                'message' => 'WhatsApp session is not connected',
            ], 409),
        ]);

        $sender = new WhapiWhatsappOtpSender(
            url: 'https://whapi.alwafaaye.com',
            apiKey: 'valid-key',
        );

        $result = $sender->send('+967771234567', '123456', OtpPurpose::PhoneVerification);

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('session is not connected', $result->error ?? '');
    }

    public function test_service_container_resolves_whapi_sender_when_driver_is_whatsapp(): void
    {
        config(['otp.driver' => 'whatsapp']);
        config(['otp.whatsapp.api_key' => 'env_injected_key']);
        config(['otp.whatsapp.url' => 'https://whapi.alwafaaye.com']);

        // Clear singleton cache if previously resolved
        $this->app->forgetInstance(OtpSenderInterface::class);

        $resolved = $this->app->make(OtpSenderInterface::class);

        $this->assertInstanceOf(WhapiWhatsappOtpSender::class, $resolved);
    }
}
