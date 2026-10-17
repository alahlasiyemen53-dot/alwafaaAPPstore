<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Contracts\OtpSenderInterface;
use App\Enums\OtpPurpose;
use App\ValueObjects\OtpSendResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WhapiWhatsappOtpSender implements OtpSenderInterface
{
    public function __construct(
        private readonly string $url = 'https://whapi.alwafaaye.com',
        private readonly string $apiKey = '',
        private readonly int $timeout = 10,
    ) {}

    public function send(string $normalizedPhone, string $code, OtpPurpose $purpose): OtpSendResult
    {
        $targetNumber = ltrim($normalizedPhone, '+');
        $maskedPhone = strlen($normalizedPhone) > 6
            ? substr($normalizedPhone, 0, 4) . '****' . substr($normalizedPhone, -3)
            : '****';

        if (empty($this->apiKey)) {
            Log::warning('Whapi WhatsApp OTP skipped: API key is not configured.', [
                'masked_phone' => $maskedPhone,
                'purpose' => $purpose->value,
            ]);

            return OtpSendResult::failure('whapi', 'مفتاح Whapi API_KEY غير مهيأ في الإعدادات.');
        }

        $message = $this->formatMessage($code, $purpose);

        try {
            $endpoint = rtrim($this->url, '/') . '/api/send';

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, [
                    'number' => $targetNumber,
                    'message' => $message,
                ]);

            if ($response->successful() && (bool) $response->json('ok', false) === true) {
                $messageId = (string) ($response->json('messageId') ?? uniqid('whapi_'));

                Log::info('Whapi WhatsApp OTP sent successfully.', [
                    'masked_phone' => $maskedPhone,
                    'purpose' => $purpose->value,
                    'message_id' => $messageId,
                ]);

                return OtpSendResult::success('whapi', $messageId);
            }

            $errorMessage = (string) ($response->json('message') ?? $response->json('error') ?? 'فشل إرسال رسالة الواتساب عبر مزود Whapi.');

            Log::error('Whapi WhatsApp OTP rejected by provider.', [
                'masked_phone' => $maskedPhone,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return OtpSendResult::failure('whapi', $errorMessage);
        } catch (Throwable $e) {
            Log::error('Whapi WhatsApp OTP exception.', [
                'masked_phone' => $maskedPhone,
                'error' => $e->getMessage(),
            ]);

            return OtpSendResult::failure('whapi', 'تعذر الاتصال بخدمة واتساب: ' . $e->getMessage());
        }
    }

    private function formatMessage(string $code, OtpPurpose $purpose): string
    {
        $intro = match ($purpose) {
            OtpPurpose::Login => 'رمز تسجيل الدخول لتطبيق الوفاء استور هو:',
            OtpPurpose::PhoneVerification => 'رمز تأكيد حسابك في تطبيق الوفاء استور هو:',
        };

        return "{$intro} {$code}\n\nصالح لمدة 5 دقائق. يرجى عدم مشاركته مع أي شخص حفاظاً على أمان حسابك.";
    }
}
