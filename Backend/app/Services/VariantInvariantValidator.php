<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class VariantInvariantValidator
{
    /**
     * @param  array<int>  $attributeValueIds
     * @return array<int, int> [attribute_id => attribute_value_id]
     */
    public function validateAttributeValues(array $attributeValueIds): array
    {
        if (empty($attributeValueIds)) {
            return [];
        }

        $values = AttributeValue::query()
            ->with('attribute')
            ->whereIn('id', $attributeValueIds)
            ->get();

        if ($values->count() !== count(array_unique($attributeValueIds))) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => ['إحدى القيم المحددة غير صالحة أو مكررة.'],
            ]);
        }

        $attributeMap = [];
        foreach ($values as $val) {
            $attribute = $val->attribute;
            if (! $val->is_active || ! ($attribute instanceof Attribute && $attribute->is_active)) {
                throw ValidationException::withMessages([
                    'attribute_value_ids' => ["الخاصية أو القيمة '{$val->value_ar}' معطلة ولا يمكن استخدامها."],
                ]);
            }

            if (isset($attributeMap[$val->attribute_id])) {
                throw ValidationException::withMessages([
                    'attribute_value_ids' => ['لا يمكن اختيار أكثر من قيمة لنفس الخاصية في المتغير الواحد.'],
                ]);
            }

            $attributeMap[$val->attribute_id] = $val->id;
        }

        return $attributeMap;
    }

    /**
     * @param  array<int>  $attributeValueIds
     */
    public function validateUniqueCombination(int $productId, array $attributeValueIds, ?int $ignoreVariantId = null): void
    {
        sort($attributeValueIds);

        $query = ProductVariant::query()
            ->where('product_id', $productId)
            ->with('attributeValues');

        if ($ignoreVariantId !== null) {
            $query->where('id', '!=', $ignoreVariantId);
        }

        $existingVariants = $query->get();

        foreach ($existingVariants as $variant) {
            $existingIds = $variant->attributeValues->pluck('id')->all();
            sort($existingIds);

            if ($existingIds === $attributeValueIds) {
                throw new ConflictHttpException('يوجد متغير آخر بنفس تركيبة الخصائص لهذا المنتج بالفعل.');
            }
        }
    }
}
