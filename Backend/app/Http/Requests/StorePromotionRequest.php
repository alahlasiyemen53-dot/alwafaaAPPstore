<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\PromotionData;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePromotionRequest extends FormRequest
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
        $scope = $this->input('scope');
        $targetIdRules = match ($scope) {
            PromotionScope::Category->value => ['required', 'integer', 'exists:categories,id'],
            PromotionScope::Brand->value => ['required', 'integer', 'exists:brands,id'],
            PromotionScope::Product->value => ['required', 'integer', 'exists:products,id'],
            PromotionScope::Variant->value => ['required', 'integer', 'exists:product_variants,id'],
            default => ['nullable'],
        };

        $targetArrayRules = match ($scope) {
            PromotionScope::Category->value,
            PromotionScope::Brand->value,
            PromotionScope::Product->value,
            PromotionScope::Variant->value => ['required', 'array', 'min:1'],
            default => ['nullable', 'array'],
        };

        return [
            'code' => ['required', 'string', 'max:50', 'unique:promotions,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', new Enum(PromotionType::class)],
            'value' => ['required', 'numeric', 'min:0.0001'],
            'scope' => ['required', new Enum(PromotionScope::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'stacking_policy' => ['nullable', 'string', 'max:50'],
            'target_ids' => $targetArrayRules,
            'target_ids.*' => $targetIdRules,
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');
            if ($type === PromotionType::Percentage->value) {
                $val = $this->input('value');
                if (is_numeric($val) && Decimal::compare(Decimal::normalize($val), '100.0000') > 0) {
                    $validator->errors()->add('value', 'نسبة الخصم لا يمكن أن تتجاوز 100%.');
                }
            }
        });
    }

    public function toDto(): PromotionData
    {
        $validated = $this->validated();
        $scope = PromotionScope::from((string) $validated['scope']);

        $targets = [];
        if ($scope !== PromotionScope::All && isset($validated['target_ids'])) {
            foreach ($validated['target_ids'] as $targetId) {
                $targets[] = [
                    'target_type' => $scope->value,
                    'target_id' => (int) $targetId,
                ];
            }
        }

        return new PromotionData(
            code: strtoupper(trim((string) $validated['code'])),
            name: (string) $validated['name'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            type: PromotionType::from((string) $validated['type']),
            value: (float) $validated['value'],
            scope: $scope,
            startsAt: isset($validated['starts_at']) ? (string) $validated['starts_at'] : null,
            endsAt: isset($validated['ends_at']) ? (string) $validated['ends_at'] : null,
            isActive: (bool) ($validated['is_active'] ?? true),
            priority: (int) ($validated['priority'] ?? 0),
            stackingPolicy: (string) ($validated['stacking_policy'] ?? 'none'),
            targets: $targets,
        );
    }
}
