<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Data\Payments\PaymentConfirmCommand;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Payments\PaymentProviderRegistry;
use App\Services\CustomerCheckoutService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmOnlinePaymentAction
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly CustomerCheckoutService $checkoutService,
    ) {}

    public function execute(Customer $customer, PaymentAttempt $attempt, string $otp): Order
    {
        if ($attempt->customer_id !== $customer->id) {
            throw new DomainException('محاولة الدفع غير موجودة.');
        }

        if ($attempt->status !== PaymentAttemptStatus::PendingOtp) {
            throw new DomainException('لا يمكن تأكيد محاولة الدفع في حالتها الحالية.');
        }

        if ($attempt->expires_at !== null && $attempt->expires_at->isPast()) {
            $attempt->update(['status' => PaymentAttemptStatus::Expired]);
            throw new DomainException('انتهت صلاحية محاولة الدفع. ابدأ عملية جديدة.');
        }

        if ($attempt->provider_purchase_id === null || $attempt->provider_purchase_id === '') {
            throw new DomainException('معرف فاتورة الدفع غير مكتمل.');
        }

        $provider = $this->providers->get($attempt->provider_code);
        $result = $provider->confirm(new PaymentConfirmCommand(
            purchaseId: (string) $attempt->provider_purchase_id,
            otp: $otp,
            requestId: $attempt->provider_request_id,
        ));

        if (! $result->success) {
            $attempt->update([
                'status' => PaymentAttemptStatus::Failed,
                'failure_message' => $result->message,
                'provider_payload' => array_merge($attempt->provider_payload ?? [], [
                    'confirm' => $this->sanitizePayload($result->raw),
                ]),
            ]);
            throw new DomainException($result->message ?? 'فشل تأكيد الدفع.');
        }

        return DB::transaction(function () use ($customer, $attempt, $result): Order {
            $checkoutIntent = is_array($attempt->provider_payload['checkout_intent'] ?? null)
                ? $attempt->provider_payload['checkout_intent']
                : [];

            $order = $this->checkoutService->createOrder($customer, [
                'customer_address_id' => $attempt->customer_address_id,
                'shipping_method' => $attempt->shipping_method,
                'payment_method' => $attempt->paymentMethod?->code ?? $attempt->provider_code->value,
                'gift_wrapping_requested' => $attempt->gift_wrapping_requested,
                'gift_message' => $attempt->gift_message,
                'coupon_code' => $attempt->coupon_code,
                'notes' => $attempt->notes,
                'cart_item_ids' => $checkoutIntent['cart_item_ids'] ?? null,
                '_payment_already_captured' => true,
                '_payment_status' => OrderPaymentStatus::Paid->value,
                '_payment_provider_reference' => $result->providerReference,
                '_payment_provider_request_id' => $result->requestId ?? $attempt->provider_request_id,
            ]);

            $attempt->update([
                'status' => PaymentAttemptStatus::Paid,
                'provider_reference' => $result->providerReference,
                'order_id' => $order->id,
                'confirmed_at' => now(),
                'provider_payload' => array_merge($attempt->provider_payload ?? [], [
                    'confirm' => $this->sanitizePayload($result->raw),
                ]),
            ]);

            return $order;
        });
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
