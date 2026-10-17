<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\OrderItemData;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewOrderPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasPermission('admin.orders.create') || $user->hasPermission('admin.orders.update'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'items' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    if (! is_array($value)) {
                        return;
                    }
                    $ids = [];
                    foreach ($value as $item) {
                        if (is_array($item) && ! empty($item['product_variant_id']) && is_numeric($item['product_variant_id'])) {
                            $ids[] = (int) $item['product_variant_id'];
                        }
                    }
                    $uniqueIds = array_unique($ids);
                    if (! empty($uniqueIds)) {
                        $foundCount = ProductVariant::query()->whereIn('id', $uniqueIds)->count();
                        if ($foundCount !== count($uniqueIds)) {
                            $fail('أحد المتغيرات المحددة غير موجود في النظام.');
                        }
                    }
                },
            ],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'يرجى اختيار العميل.',
            'customer_id.exists' => 'العميل المحدد غير موجود أو غير نشط.',
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

    /** @return array<int, OrderItemData> */
    public function toOrderItemsData(): array
    {
        $items = (array) $this->validated('items', []);

        return array_map(
            static fn (array $item): OrderItemData => OrderItemData::fromArray([
                'product_variant_id' => $item['product_variant_id'],
                'quantity' => $item['quantity'],
                'unit_id' => $item['unit_id'] ?? null,
                'discount_amount' => 0.0,
            ]),
            $items
        );
    }
}
