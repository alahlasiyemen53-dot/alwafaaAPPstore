<?php

declare(strict_types=1);

namespace App\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\PaymentProviderCode;
use App\Payments\Providers\FakePaymentProvider;
use DomainException;
use Illuminate\Contracts\Container\Container;

final class PaymentProviderRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function get(PaymentProviderCode|string $code): PaymentProviderInterface
    {
        $providerCode = $code instanceof PaymentProviderCode
            ? $code
            : PaymentProviderCode::from((string) $code);

        if ($providerCode === PaymentProviderCode::Cod) {
            throw new DomainException('الدفع عند الاستلام لا يستخدم مزوّد دفع خارجي.');
        }

        $map = (array) config('payments.providers', []);
        $binding = $map[$providerCode->value] ?? null;
        if (! is_string($binding) || $binding === '') {
            throw new DomainException('مزوّد الدفع غير مهيأ في النظام.');
        }

        if ($binding === FakePaymentProvider::class) {
            return new FakePaymentProvider($providerCode);
        }

        /** @var PaymentProviderInterface $provider */
        $provider = $this->container->make($binding);

        return $provider;
    }
}
