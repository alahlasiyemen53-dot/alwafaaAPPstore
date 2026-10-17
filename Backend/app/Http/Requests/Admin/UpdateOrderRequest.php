<?php

namespace App\Http\Requests\Admin;

use App\DTOs\UpdateOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'coupon_code' => $this->filled('coupon_code')
                ? strtoupper(trim((string) $this->input('coupon_code')))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = $this->input('customer_id');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'customer_address_id' => [
                'required',
                'integer',
                Rule::exists('customer_addresses', 'id')->where('customer_id', $customerId),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'gift_wrapping_requested' => ['nullable', 'boolean'],
            'gift_message' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                Rule::exists('product_variants', 'id'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var \App\Services\GiftWrappingService $service */
            $service = app(\App\Services\GiftWrappingService::class);

            $wrappingRequested = filter_var($this->input('gift_wrapping_requested'), FILTER_VALIDATE_BOOLEAN);
            $giftMessage = $this->input('gift_message');
            $hasMessage = $giftMessage !== null && trim((string) $giftMessage) !== '';

            if ($wrappingRequested && ! $service->isEnabled()) {
                $validator->errors()->add('gift_wrapping_requested', 'خدمة تغليف الهدايا غير مفعّلة في النظام حالياً.');
            }

            if ($hasMessage) {
                if (! $wrappingRequested) {
                    $validator->errors()->add('gift_message', 'لا يمكن إرفاق رسالة هدية دون تفعيل خيار تغليف الهدية.');
                } elseif (! $service->isMessageEnabled()) {
                    $validator->errors()->add('gift_message', 'خدمة إرفاق بطاقة رسالة الهدية غير مفعّلة في النظام حالياً.');
                } elseif (mb_strlen(trim(strip_tags((string) $giftMessage))) > $service->getMaxMessageLength()) {
                    $validator->errors()->add('gift_message', "طول رسالة الهدية يتجاوز الحد الأقصى المسموح به ({$service->getMaxMessageLength()} حرفاً).");
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'يرجى اختيار العميل.',
            'customer_id.exists' => 'العميل المحدد غير موجود أو غير نشط.',
            'customer_address_id.required' => 'يرجى تحديد عنوان التوصيل للعميل.',
            'customer_address_id.exists' => 'عنوان التوصيل المحدد غير صالح أو لا ينتمي لهذا العميل.',
            'warehouse_id.required' => 'يرجى اختيار مستودع التنفيذ.',
            'warehouse_id.exists' => 'المستودع المحدد غير موجود أو غير نشط.',
            'currency_id.required' => 'يرجى اختيار عملة الطلب.',
            'currency_id.exists' => 'العملة المحددة غير موجودة أو غير نشطة.',
            'items.required' => 'يجب إضافة بند واحد على الأقل للطلب.',
            'items.min' => 'يجب إضافة بند واحد على الأقل للطلب.',
            'items.*.product_variant_id.required' => 'يرجى تحديد المنتج/المتغير لكل سطر.',
            'items.*.product_variant_id.exists' => 'متغير المنتج المحدد غير موجود.',
            'items.*.quantity.required' => 'يرجى تحديد الكمية المطلوبة.',
            'items.*.quantity.gt' => 'يجب أن تكون الكمية المطلوبة أكبر من الصفر.',
        ];
    }

    public function toDto(): UpdateOrderData
    {
        return UpdateOrderData::fromArray($this->validated());
    }
}
