<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Data\Payments\PaymentCaptureCommand;
use App\Data\Payments\PaymentCaptureResult;
use App\Data\Payments\PaymentConfirmCommand;
use App\Data\Payments\PaymentInitiateCommand;
use App\Data\Payments\PaymentInitiateResult;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use Illuminate\Support\Str;

/**
 * Fake provider for local/dev/tests — no outbound network calls.
 * Accepts purchase/OTP code from config (default 123456) for Jaib (step1) and Floosak (step2).
 */
final class FakePaymentProvider extends AbstractPaymentProvider
{
    public function __construct(
        private readonly ?PaymentProviderCode $boundCode = null,
    ) {}

    public function code(): PaymentProviderCode
    {
        if ($this->boundCode !== null) {
            return $this->boundCode;
        }

        return PaymentProviderCode::from((string) config('payments.fake.provider_code', 'jaib'));
    }

    public function flowType(): PaymentFlowType
    {
        return match ($this->code()) {
            PaymentProviderCode::Floosak => PaymentFlowType::Step2,
            PaymentProviderCode::Jaib => PaymentFlowType::Step1,
            default => PaymentFlowType::from((string) config('payments.fake.flow_type', 'step1')),
        };
    }

    public function capture(PaymentCaptureCommand $command): PaymentCaptureResult
    {
        $code = preg_replace('/\D+/', '', $command->paymentCode) ?? '';
        $expected = (string) config('payments.fake.otp', '123456');
        if ($code !== $expected) {
            return PaymentCaptureResult::failed('فشل الدفع: رمز الدفع غير صالح (وهمي).');
        }

        return PaymentCaptureResult::succeeded(
            providerReference: 'FAKE-REF-'.Str::upper(Str::random(8)),
            requestId: $command->requestId,
            message: 'تمت العملية بنجاح (وهمي)',
        );
    }

    public function initiate(PaymentInitiateCommand $command): PaymentInitiateResult
    {
        if (filter_var(config('payments.fake.fail_initiate', false), FILTER_VALIDATE_BOOLEAN)) {
            return PaymentInitiateResult::failed('فشل الدفع: الرصيد غير كافٍ (وهمي).');
        }

        $digits = preg_replace('/\D+/', '', $command->customerPhone) ?? '';
        if ($digits !== '' && str_ends_with($digits, '0000')) {
            return PaymentInitiateResult::failed('فشل الدفع: الرصيد غير كافٍ (وهمي).');
        }

        return PaymentInitiateResult::pending(
            purchaseId: (string) random_int(100000, 999999),
            requestId: $command->requestId,
            providerReference: 'FAKE-PEND-'.Str::upper(Str::random(6)),
            message: 'بانتظار OTP (وهمي)',
        );
    }

    public function confirm(PaymentConfirmCommand $command): PaymentCaptureResult
    {
        $expected = (string) config('payments.fake.otp', '123456');
        $otp = preg_replace('/\D+/', '', $command->otp) ?? '';
        if ($otp !== $expected) {
            return PaymentCaptureResult::failed('فشل الدفع: رمز التحقق غير صحيح (وهمي).');
        }

        return PaymentCaptureResult::succeeded(
            providerReference: 'FAKE-CONF-'.Str::upper(Str::random(8)),
            requestId: $command->requestId,
            message: 'تم التأكيد بنجاح (وهمي)',
        );
    }
}
