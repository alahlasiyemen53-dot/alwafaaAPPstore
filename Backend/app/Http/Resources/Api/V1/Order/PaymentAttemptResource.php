<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use App\Models\CustomerPaymentMethod;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentAttempt */
final class PaymentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $methodCode = $this->paymentMethod?->code;
        $methodPresentation = CustomerPaymentMethod::presentationForCode($methodCode);

        return [
            'uuid' => $this->uuid,
            'status' => $this->status?->value ?? (string) $this->status,
            'provider_code' => $this->provider_code?->value ?? (string) $this->provider_code,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'customer_phone' => $this->maskPhone($this->customer_phone),
            'payment_method' => [
                'code' => $methodPresentation['code'],
                'name_ar' => $methodPresentation['name_ar'],
            ],
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $lastFour = substr($digits, -4);

        return str_repeat('*', max(0, strlen($digits) - 4)).$lastFour;
    }
}
