<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\CouponData;
use App\Enums\CouponDiscountType;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $scope = (string) $this->input('scope', 'all');
        $targetIdRules = match ($scope) {
            'category' => ['required', 'integer', 'exists:categories,id'],
            'brand' => ['required', 'integer', 'exists:brands,id'],
            'product' => ['required', 'integer', 'exists:products,id'],
            'variant' => ['required', 'integer', 'exists:product_variants,id'],
            default => ['nullable'],
        };

        $targetArrayRules = match ($scope) {
            'category', 'brand', 'product', 'variant' => ['required', 'array', 'min:1'],
            default => ['nullable', 'array'],
        };

        return [
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', new Enum(CouponDiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.0001'],
            'scope' => ['nullable', 'string', Rule::in(['all', 'category', 'brand', 'product', 'variant'])],
            'target_ids' => $targetArrayRules,
            'target_ids.*' => $targetIdRules,
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'is_compatible_with_promotions' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('discount_type');
            if ($type === CouponDiscountType::Percentage->value) {
                $val = $this->input('discount_value');
                if (is_numeric($val) && Decimal::compare(Decimal::normalize($val), '100.0000') > 0) {
                    $validator->errors()->add('discount_value', 'نسبة الخصم لا يمكن أن تتجاوز 100%.');
                }
            }
        });
    }

    public function toDto(): CouponData
    {
        $validated = $this->validated();
        $scope = (string) ($validated['scope'] ?? 'all');

        $targets = [];
        if ($scope !== 'all' && isset($validated['target_ids'])) {
            foreach ($validated['target_ids'] as $targetId) {
                $targets[] = [
                    'target_type' => $scope,
                    'target_id' => (int) $targetId,
                ];
            }
        }

        return new CouponData(
            code: strtoupper(trim((string) $validated['code'])),
            name: (string) $validated['name'],
            discountType: CouponDiscountType::from((string) $validated['discount_type']),
            discountValue: (float) $validated['discount_value'],
            minimumOrderAmount: isset($validated['minimum_order_amount']) ? (float) $validated['minimum_order_amount'] : null,
            maximumDiscountAmount: isset($validated['maximum_discount_amount']) ? (float) $validated['maximum_discount_amount'] : null,
            startsAt: isset($validated['starts_at']) ? (string) $validated['starts_at'] : null,
            endsAt: isset($validated['ends_at']) ? (string) $validated['ends_at'] : null,
            isActive: (bool) ($validated['is_active'] ?? true),
            usageLimit: isset($validated['usage_limit']) ? (int) $validated['usage_limit'] : null,
            perCustomerLimit: isset($validated['per_customer_limit']) ? (int) $validated['per_customer_limit'] : null,
            isCompatibleWithPromotions: (bool) ($validated['is_compatible_with_promotions'] ?? false),
            scope: $scope,
            targets: $targets,
        );
    }
}
