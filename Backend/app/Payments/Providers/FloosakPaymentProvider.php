<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Data\Payments\PaymentCaptureResult;
use App\Data\Payments\PaymentConfirmCommand;
use App\Data\Payments\PaymentInitiateCommand;
use App\Data\Payments\PaymentInitiateResult;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use Illuminate\Support\Facades\Http;

final class FloosakPaymentProvider extends AbstractPaymentProvider
{
    public function code(): PaymentProviderCode
    {
        return PaymentProviderCode::Floosak;
    }

    public function flowType(): PaymentFlowType
    {
        return PaymentFlowType::Step2;
    }

    public function initiate(PaymentInitiateCommand $command): PaymentInitiateResult
    {
        $baseUrl = rtrim((string) config('payments.floosak.base_url'), '/');
        $response = Http::timeout((int) config('payments.floosak.timeout', 30))
            ->withToken((string) config('payments.floosak.api_key'))
            ->withHeaders([
                'Accept' => 'application/json',
                'x-channel' => (string) config('payments.floosak.channel', 'merchant'),
            ])
            ->asJson()
            ->post($baseUrl.'/api/v1/merchant/p2mcl', [
                'source_wallet_id' => (int) config('payments.floosak.source_wallet_id'),
                'request_id' => $command->requestId,
                'target_phone' => $command->customerPhone,
                'amount' => (float) $command->amount,
                'purpose' => $command->purpose,
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || ($payload['is_success'] ?? false) !== true) {
            $message = (string) ($payload['message'] ?? 'فشل إنشاء فاتورة فلوسك المعلّقة.');

            return PaymentInitiateResult::failed($message, is_array($payload) ? $payload : []);
        }

        $purchaseId = (string) data_get($payload, 'data.id', '');
        if ($purchaseId === '') {
            return PaymentInitiateResult::failed('استجابة فلوسك بدون معرف فاتورة.', is_array($payload) ? $payload : []);
        }

        return PaymentInitiateResult::pending(
            purchaseId: $purchaseId,
            requestId: $command->requestId,
            providerReference: (string) data_get($payload, 'data.reference_id'),
            message: 'بانتظار رمز التحقق من العميل',
            raw: is_array($payload) ? $payload : [],
        );
    }

    public function confirm(PaymentConfirmCommand $command): PaymentCaptureResult
    {
        $baseUrl = rtrim((string) config('payments.floosak.base_url'), '/');
        $response = Http::timeout((int) config('payments.floosak.timeout', 30))
            ->withToken((string) config('payments.floosak.api_key'))
            ->withHeaders([
                'Accept' => 'application/json',
                'x-channel' => (string) config('payments.floosak.channel', 'merchant'),
            ])
            ->asJson()
            ->post($baseUrl.'/api/v1/merchant/p2mcl/confirm', [
                'otp' => $command->otp,
                'purchase_id' => is_numeric($command->purchaseId)
                    ? (int) $command->purchaseId
                    : $command->purchaseId,
            ]);

        $payload = $response->json() ?? [];
        if (! $response->successful() || ($payload['is_success'] ?? false) !== true) {
            $message = (string) ($payload['message'] ?? 'فشل تأكيد دفع فلوسك.');

            return PaymentCaptureResult::failed($message, is_array($payload) ? $payload : []);
        }

        $statusEn = strtolower((string) data_get($payload, 'data.status.en', ''));
        if ($statusEn !== '' && $statusEn !== 'completed') {
            return PaymentCaptureResult::failed('عملية فلوسك لم تكتمل بعد.', is_array($payload) ? $payload : []);
        }

        $reference = (string) data_get($payload, 'data.reference_id', data_get($payload, 'data.id', ''));
        if ($reference === '') {
            return PaymentCaptureResult::failed('استجابة تأكيد فلوسك بدون مرجع.', is_array($payload) ? $payload : []);
        }

        return PaymentCaptureResult::succeeded(
            providerReference: $reference,
            requestId: $command->requestId,
            message: 'تم تأكيد الدفع عبر فلوسك',
            raw: is_array($payload) ? $payload : [],
        );
    }
}
