<?php

namespace App\Repositories;

use App\Data\AttributeData;
use App\Data\AttributeValueData;
use App\Models\Attribute;
use App\Models\AttributeValue;

final class AttributeRepository
{
    public function create(AttributeData $data): Attribute
    {
        return Attribute::query()->create([
            'name_ar' => $data->nameAr,
            'name_en' => $data->nameEn,
            'code' => $data->code,
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(Attribute $attribute, AttributeData $data): Attribute
    {
        $attribute->update([
            'name_ar' => $data->nameAr,
            'name_en' => $data->nameEn,
            'code' => $data->code,
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);

        return $attribute->fresh();
    }

    public function setStatus(Attribute $attribute, bool $isActive): Attribute
    {
        $attribute->update(['is_active' => $isActive]);

        return $attribute->fresh();
    }

    public function createValue(AttributeValueData $data): AttributeValue
    {
        return AttributeValue::query()->create([
            'attribute_id' => $data->attributeId,
            'value_ar' => $data->valueAr,
            'value_en' => $data->valueEn,
            'code' => $data->code,
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);
    }

    public function updateValue(AttributeValue $value, AttributeValueData $data): AttributeValue
    {
        $value->update([
            'value_ar' => $data->valueAr,
            'value_en' => $data->valueEn,
            'code' => $data->code,
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);

        return $value->fresh();
    }

    public function setValueStatus(AttributeValue $value, bool $isActive): AttributeValue
    {
        $value->update(['is_active' => $isActive]);

        return $value->fresh();
    }
}
