<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\CouponDiscountType;

final readonly class CouponData
{
    public function __construct(
        public string $code,
        public string $name,
        public CouponDiscountType $discountType,
        public float|string $discountValue,
        public float|string|null $minimumOrderAmount = null,
        public float|string|null $maximumDiscountAmount = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public bool $isActive = true,
        public ?int $usageLimit = null,
        public ?int $perCustomerLimit = null,
        public bool $isCompatibleWithPromotions = false,
        public string $scope = 'all',
        public array $targets = [],
    ) {}
}
