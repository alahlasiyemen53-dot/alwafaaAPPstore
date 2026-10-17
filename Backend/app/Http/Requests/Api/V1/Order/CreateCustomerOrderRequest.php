<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Http\Requests\Api\V1\Order\Concerns\ValidatesCustomerPaymentFields;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CreateCustomerOrderRequest extends FormRequest
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
            'payment_method' => $this->activePaymentMethodRules(),
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
            'customer_address_id.required' => 'عنوان التوصيل مطلوب لإتمام الطلب.',
            'customer_address_id.integer' => 'معرف العنوان يجب أن يكون عدداً صحيحاً.',
            'customer_address_id.exists' => 'عنوان التوصيل المحدد غير موجود.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'payment.mobile.required_if' => 'رقم الجوال مطلوب لدفع جيب.',
            'payment.code.required_if' => 'رمز الشراء مطلوب لدفع جيب.',
            'shipping_method.exists' => 'طريقة التوصيل المحددة غير متاحة حالياً.',
            'gift_wrapping_requested.boolean' => 'تحديد تغليف الهدية يجب أن يكون قيمة منطقية (صح/خطأ).',
            'gift_message.max' => 'رسالة الإهداء لا يمكن أن تتجاوز 250 حرفاً.',
            'notes.max' => 'الملاحظات لا يمكن أن تتجاوز 500 حرف.',
            'cart_item_ids.min' => 'يرجى اختيار منتج واحد على الأقل للشراء.',
            'cart_item_ids.*.integer' => 'معرف عنصر السلة يجب أن يكون عدداً صحيحاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات إنشاء الطلب غير صالحة.'
            )
        );
    }
}
