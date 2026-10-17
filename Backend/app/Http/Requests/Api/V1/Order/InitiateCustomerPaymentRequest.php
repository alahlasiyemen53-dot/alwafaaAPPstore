<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\Order\Concerns\ValidatesCustomerPaymentFields;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class InitiateCustomerPaymentRequest extends FormRequest
{
    use ValidatesCustomerPaymentFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->sharedCheckoutFieldRules(), [
            'payment_method' => $this->step2PaymentMethodRules(),
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('shipping_method')) {
            $this->merge(['shipping_method' => 'standard']);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_address_id.required' => 'عنوان التوصيل مطلوب لبدء الدفع.',
            'customer_address_id.exists' => 'عنوان التوصيل المحدد غير موجود.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'payment_method.exists' => 'طريقة الدفع المحددة لا تدعم الفاتورة المعلّقة.',
            'shipping_method.exists' => 'طريقة التوصيل المحددة غير متاحة حالياً.',
            'cart_item_ids.min' => 'يرجى اختيار منتج واحد على الأقل للشراء.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات بدء الدفع غير صالحة.'
            )
        );
    }
}
