<?php

namespace App\Data;

use App\Enums\ProductStatus;

final readonly class ProductListData
{
    public function __construct(
        public ?string $search,
        public ?int $brandId,
        public ?int $categoryId,
        public ?ProductStatus $status,
        public string $sort,
        public string $direction,
        public ?bool $isBestSeller = null,
        public ?bool $isNew = null,
        public ?bool $isExclusive = null,
    ) {}

    public static function fromValidated(array $filters): self
    {
        return new self(
            $filters['search'] ?? null,
            isset($filters['brand_id']) ? (int) $filters['brand_id'] : null,
            isset($filters['category_id']) ? (int) $filters['category_id'] : null,
            isset($filters['status']) ? ProductStatus::from($filters['status']) : null,
            $filters['sort'] ?? 'created_at',
            $filters['direction'] ?? 'desc',
            self::parseBooleanFilter($filters['best_seller'] ?? null),
            self::parseBooleanFilter($filters['new'] ?? null),
            self::parseBooleanFilter($filters['exclusive'] ?? null),
        );
    }

    private static function parseBooleanFilter(mixed $value): ?bool
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        if (in_array($value, ['yes', '1', 1, true, 'true'], true)) {
            return true;
        }

        if (in_array($value, ['no', '0', 0, false, 'false'], true)) {
            return false;
        }

        return null;
    }
}
