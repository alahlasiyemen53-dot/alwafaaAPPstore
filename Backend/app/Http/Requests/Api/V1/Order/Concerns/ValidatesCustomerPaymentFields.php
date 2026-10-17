<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order\Concerns;

use App\Enums\PaymentFlowType;
use App\Models\CustomerPaymentMethod;
use Illuminate\Validation\Rule;

trait ValidatesCustomerPaymentFields
{
    /**
     * @return array<int, mixed>
     */
    protected function activePaymentMethodRules(bool $required = true, bool $nullable = false): array
    {
        $rules = ['string', 'max:64'];

        if ($nullable) {
            $rules[] = 'nullable';
        } elseif ($required) {
            $rules[] = 'required';
        }

        $rules[] = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ((string) $value === 'cod') {
                return;
            }

            if (! CustomerPaymentMethod::query()->where('code', (string) $value)->where('is_active', true)->exists()) {
                $fail('طريقة الدفع المحددة غير متاحة.');
            }
        };

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    protected function step2PaymentMethodRules(): array
    {
        return [
            'required',
            'string',
            'max:64',
            Rule::exists('customer_payment_methods', 'code')->where(function ($query): void {
                $query->where('is_active', true)->where('flow_type', PaymentFlowType::Step2->value);
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedCheckoutFieldRules(bool $addressRequired = true): array
    {
        return [
            'customer_address_id' => [
                $addressRequired ? 'required' : 'nullable',
                'integer',
                'exists:customer_addresses,id',
            ],
            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'shipping_method' => [
                'nullable',
                'string',
                'max:64',
                Rule::exists('delivery_methods', 'code')->where('is_active', true),
            ],
            'gift_wrapping_requested' => [
                'nullable',
                'boolean',
            ],
            'gift_message' => [
                'nullable',
                'string',
                'max:250',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'payment' => [
                'nullable',
                'array',
            ],
            'payment.code' => [
                'required_if:payment_method,jaib',
                'string',
                'max:64',
            ],
            'cart_item_ids' => [
                'nullable',
                'array',
                'min:1',
            ],
            'cart_item_ids.*' => [
                'integer',
                'distinct',
                'min:1',
            ],
        ];
    }
}
