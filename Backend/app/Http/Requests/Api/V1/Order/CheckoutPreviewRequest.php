<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\Order\Concerns\ValidatesCustomerPaymentFields;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class CheckoutPreviewRequest extends FormRequest
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
        return [
            'customer_address_id' => [
                'nullable',
                'integer',
                'exists:customer_addresses,id',
            ],
            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
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
            'payment_method' => $this->activePaymentMethodRules(required: false, nullable: true),
            'shipping_method' => [
                'nullable',
                'string',
                'max:64',
                Rule::exists('delivery_methods', 'code')->where('is_active', true),
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_address_id.integer' => 'معرف العنوان يجب أن يكون عدداً صحيحاً.',
            'customer_address_id.exists' => 'عنوان التوصيل المحدد غير موجود.',
            'coupon_code.max' => 'كود الكوبون لا يمكن أن يتجاوز 50 حرفاً.',
            'gift_wrapping_requested.boolean' => 'تحديد تغليف الهدية يجب أن يكون قيمة منطقية (صح/خطأ).',
            'gift_message.max' => 'رسالة الإهداء لا يمكن أن تتجاوز 250 حرفاً.',
            'shipping_method.exists' => 'طريقة التوصيل المحددة غير متاحة حالياً.',
            'cart_item_ids.min' => 'يرجى اختيار منتج واحد على الأقل للشراء.',
            'cart_item_ids.*.integer' => 'معرف عنصر السلة يجب أن يكون عدداً صحيحاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات معاينة الطلب غير صالحة.'
            )
        );
    }
}
