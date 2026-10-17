<?php

namespace App\Data;

final readonly class UpdateVariantPriceData
{
    public function __construct(
        public string $price,
        public ?string $compareAtPrice,
        public bool $isActive = true,
    ) {}

    public static function fromValidated(array $attributes): self
    {
        return new self(
            price: (string) $attributes['price'],
            compareAtPrice: isset($attributes['compare_at_price']) && $attributes['compare_at_price'] !== ''
                ? (string) $attributes['compare_at_price']
                : null,
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }
}
