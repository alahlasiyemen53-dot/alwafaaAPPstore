<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BundlePricingType;

final readonly class BundleData
{
    /**
     * @param  array<int, BundleItemData>  $items
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public BundlePricingType $pricingType,
        public float|string $value,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public bool $isActive = true,
        public int $priority = 0,
        public array $items = [],
    ) {}
}
