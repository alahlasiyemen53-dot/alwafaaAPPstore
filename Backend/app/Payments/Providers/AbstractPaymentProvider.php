<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Data\Payments\PaymentCaptureCommand;
use App\Data\Payments\PaymentCaptureResult;
use App\Data\Payments\PaymentConfirmCommand;
use App\Data\Payments\PaymentInitiateCommand;
use App\Data\Payments\PaymentInitiateResult;
use DomainException;

abstract class AbstractPaymentProvider implements PaymentProviderInterface
{
    public function capture(PaymentCaptureCommand $command): PaymentCaptureResult
    {
        throw new DomainException('مزوّد الدفع هذا لا يدعم الدفع بخطوة واحدة.');
    }

    public function initiate(PaymentInitiateCommand $command): PaymentInitiateResult
    {
        throw new DomainException('مزوّد الدفع هذا لا يدعم بدء فاتورة معلّقة.');
    }

    public function confirm(PaymentConfirmCommand $command): PaymentCaptureResult
    {
        throw new DomainException('مزوّد الدفع هذا لا يدعم تأكيد OTP.');
    }
}
