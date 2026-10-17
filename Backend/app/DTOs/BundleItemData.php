<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class BundleItemData
{
    public function __construct(
        public int $productVariantId,
        public float|string $quantity = 1.0,
    ) {}
}
