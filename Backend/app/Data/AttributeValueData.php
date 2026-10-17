<?php

namespace App\Data;

final readonly class AttributeValueData
{
    public function __construct(
        public int $attributeId,
        public string $valueAr,
        public ?string $valueEn,
        public string $code,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public static function fromValidated(int $attributeId, array $attributes): self
    {
        return new self(
            attributeId: $attributeId,
            valueAr: $attributes['value_ar'],
            valueEn: $attributes['value_en'] ?? null,
            code: strtolower(trim($attributes['code'])),
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }
}
