<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Data\Payments\PaymentInitiateCommand;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentFlowType;
use App\Models\Customer;
use App\Models\CustomerPaymentMethod;
use App\Models\PaymentAttempt;
use App\Payments\PaymentProviderRegistry;
use App\Services\CustomerCheckoutService;
use DomainException;
use Illuminate\Support\Str;

final class InitiateOnlinePaymentAction
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly CustomerCheckoutService $checkoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $intent
     */
    public function execute(Customer $customer, array $intent): PaymentAttempt
    {
        $methodCode = (string) ($intent['payment_method'] ?? '');
        $method = CustomerPaymentMethod::query()
            ->where('code', $methodCode)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            throw new DomainException('طريقة الدفع المحددة غير متاحة.');
        }

        if ($method->flow_type !== PaymentFlowType::Step2) {
            throw new DomainException('طريقة الدفع هذه لا تتطلب بدء فاتورة معلّقة.');
        }

        $phone = preg_replace('/\D+/', '', (string) ($customer->phone ?? '')) ?? '';
        if ($phone === '') {
            throw new DomainException('رقم جوال الحساب مطلوب لبدء الدفع.');
        }

        $preview = $this->checkoutService->preview($customer, [
            'customer_address_id' => $intent['customer_address_id'] ?? null,
            'shipping_method' => $intent['shipping_method'] ?? null,
            'coupon_code' => $intent['coupon_code'] ?? null,
            'gift_wrapping_requested' => $intent['gift_wrapping_requested'] ?? false,
            'gift_message' => $intent['gift_message'] ?? null,
            'payment_method' => $method->code,
            'cart_item_ids' => $intent['cart_item_ids'] ?? null,
        ]);

        $amount = number_format((float) ($preview['pricing']['total'] ?? 0), 4, '.', '');
        if (bccomp($amount, '0', 4) <= 0) {
            throw new DomainException('مبلغ الدفع غير صالح.');
        }

        $currencyCode = (string) ($preview['currency']['code'] ?? 'YER');
        $requestId = (string) Str::uuid();

        $provider = $this->providers->get($method->provider_code);
        $result = $provider->initiate(new PaymentInitiateCommand(
            requestId: $requestId,
            customerPhone: $phone,
            amount: $amount,
            currencyCode: $currencyCode,
            purpose: 'دفع طلب الوفاء استور',
        ));

        if (! $result->success || $result->purchaseId === null) {
            throw new DomainException($result->message ?? 'فشل بدء عملية الدفع.');
        }

        return PaymentAttempt::query()->create([
            'customer_id' => $customer->id,
            'customer_payment_method_id' => $method->id,
            'provider_code' => $method->provider_code,
            'status' => PaymentAttemptStatus::PendingOtp,
            'provider_request_id' => $result->requestId ?? $requestId,
            'provider_purchase_id' => $result->purchaseId,
            'provider_reference' => $result->providerReference,
            'customer_phone' => $phone,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'customer_address_id' => $intent['customer_address_id'] ?? null,
            'shipping_method' => $intent['shipping_method'] ?? null,
            'gift_wrapping_requested' => (bool) ($intent['gift_wrapping_requested'] ?? false),
            'gift_message' => $intent['gift_message'] ?? null,
            'coupon_code' => $intent['coupon_code'] ?? null,
            'notes' => $intent['notes'] ?? null,
            'provider_payload' => [
                'checkout_intent' => [
                    'cart_item_ids' => is_array($intent['cart_item_ids'] ?? null)
                        ? array_values(array_map('intval', $intent['cart_item_ids']))
                        : null,
                ],
                'initiate' => $this->sanitizePayload($result->raw),
            ],
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        unset($payload['otp'], $payload['password'], $payload['api_key'], $payload['key']);

        return $payload;
    }
}
