<?php

namespace App\Data;

final readonly class AttributeData
{
    public function __construct(
        public string $nameAr,
        public ?string $nameEn,
        public string $code,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public static function fromValidated(array $attributes): self
    {
        return new self(
            nameAr: $attributes['name_ar'],
            nameEn: $attributes['name_en'] ?? null,
            code: strtolower(trim($attributes['code'])),
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }
}
