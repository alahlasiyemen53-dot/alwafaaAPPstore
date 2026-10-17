<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentCaptureCommand;
use App\Data\Payments\PaymentCaptureResult;
use App\Data\Payments\PaymentConfirmCommand;
use App\Data\Payments\PaymentInitiateCommand;
use App\Data\Payments\PaymentInitiateResult;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;

interface PaymentProviderInterface
{
    public function code(): PaymentProviderCode;

    public function flowType(): PaymentFlowType;

    public function capture(PaymentCaptureCommand $command): PaymentCaptureResult;

    public function initiate(PaymentInitiateCommand $command): PaymentInitiateResult;

    public function confirm(PaymentConfirmCommand $command): PaymentCaptureResult;
}
