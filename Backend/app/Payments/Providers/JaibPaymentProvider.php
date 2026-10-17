<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Data\Payments\PaymentCaptureCommand;
use App\Data\Payments\PaymentCaptureResult;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class JaibPaymentProvider extends AbstractPaymentProvider
{
    public function code(): PaymentProviderCode
    {
        return PaymentProviderCode::Jaib;
    }

    public function flowType(): PaymentFlowType
    {
        return PaymentFlowType::Step1;
    }

    public function capture(PaymentCaptureCommand $command): PaymentCaptureResult
    {
        $auth = $this->authenticate();
        if ($auth === null) {
            return PaymentCaptureResult::failed('تعذر المصادقة مع محفظة جيب.');
        }

        $baseUrl = rtrim((string) config('payments.jaib.base_url'), '/');
        $response = Http::timeout((int) config('payments.jaib.timeout', 30))
            ->withToken($auth['access_token'])
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/api/v1/BuyOnline/ExeBuy', [
                'pinApi' => $auth['pin_api'],
                'mobile' => $command->customerPhone,
                'requestID' => $command->requestId,
                'code' => $command->paymentCode,
                'amount' => (float) $command->amount,
                'currencyCode' => $command->currencyCode,
                'notes' => $command->notes ?? '',
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || ($payload['success'] ?? false) !== true) {
            $message = (string) data_get($payload, 'error.message', 'فشل تنفيذ الدفع عبر جيب.');

            return PaymentCaptureResult::failed($message, is_array($payload) ? $payload : []);
        }

        $reference = (string) data_get($payload, 'result.referenceID', '');
        if ($reference === '') {
            return PaymentCaptureResult::failed('استجابة جيب بدون مرجع عملية.', is_array($payload) ? $payload : []);
        }

        return PaymentCaptureResult::succeeded(
            providerReference: $reference,
            requestId: (string) data_get($payload, 'result.requestID', $command->requestId),
            message: (string) data_get($payload, 'result.msg'),
            raw: is_array($payload) ? $payload : [],
        );
    }

    /**
     * @return array{access_token: string, pin_api: string}|null
     */
    private function authenticate(): ?array
    {
        $cacheKey = 'payments.jaib.auth';
        /** @var array{access_token: string, pin_api: string}|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['access_token'] ?? '') !== '' && ($cached['pin_api'] ?? '') !== '') {
            return $cached;
        }

        $baseUrl = rtrim((string) config('payments.jaib.base_url'), '/');
        $response = Http::timeout((int) config('payments.jaib.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->post($baseUrl.'/api/v1/TokenAuth/LogAPI', [
                'userName' => (string) config('payments.jaib.username'),
                'password' => (string) config('payments.jaib.password'),
                'agentCode' => (string) config('payments.jaib.agent_code'),
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || ($payload['success'] ?? false) !== true) {
            return null;
        }

        $accessToken = (string) data_get($payload, 'result.accessToken', '');
        $pinApi = (string) data_get($payload, 'result.pinApi', '');
        if ($accessToken === '' || $pinApi === '') {
            return null;
        }

        $expire = (int) data_get($payload, 'result.expire', 3600);
        $ttl = max(60, $expire - 60);
        $auth = [
            'access_token' => $accessToken,
            'pin_api' => $pinApi,
        ];
        Cache::put($cacheKey, $auth, $ttl);

        return $auth;
    }
}
