<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\BundleData;
use App\DTOs\BundleItemData;
use App\Enums\BundlePricingType;
use App\Models\Bundle;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateBundleRequest extends FormRequest
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
        $bundle = $this->route('bundle');
        $bundleId = $bundle instanceof Bundle ? $bundle->id : (int) $bundle;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('bundles', 'code')->ignore($bundleId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pricing_type' => ['required', new Enum(BundlePricingType::class)],
            'value' => ['required', 'numeric', 'min:0.0001'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('pricing_type');
            if ($type === BundlePricingType::PercentageDiscount->value) {
                $val = $this->input('value');
                if (is_numeric($val) && Decimal::compare(Decimal::normalize($val), '100.0000') > 0) {
                    $validator->errors()->add('value', 'نسبة الخصم لا يمكن أن تتجاوز 100%.');
                }
            }
        });
    }

    public function toDto(): BundleData
    {
        $validated = $this->validated();

        $items = [];
        foreach ($validated['items'] as $item) {
            $items[] = new BundleItemData(
                productVariantId: (int) $item['product_variant_id'],
                quantity: (float) $item['quantity'],
            );
        }

        return new BundleData(
            code: strtoupper(trim((string) $validated['code'])),
            name: (string) $validated['name'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            pricingType: BundlePricingType::from((string) $validated['pricing_type']),
            value: (float) $validated['value'],
            startsAt: isset($validated['starts_at']) ? (string) $validated['starts_at'] : null,
            endsAt: isset($validated['ends_at']) ? (string) $validated['ends_at'] : null,
            isActive: (bool) ($validated['is_active'] ?? true),
            priority: (int) ($validated['priority'] ?? 0),
            items: $items,
        );
    }
}
