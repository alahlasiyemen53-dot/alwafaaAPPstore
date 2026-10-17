<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;

final readonly class PromotionData
{
    /**
     * @param  array<int, array{target_type: string, target_id: int}>  $targets
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public PromotionType $type,
        public float|string $value,
        public PromotionScope $scope,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $isActive = true,
        public int $priority = 0,
        public string $stackingPolicy = 'none',
        public array $targets = [],
    ) {}
}
