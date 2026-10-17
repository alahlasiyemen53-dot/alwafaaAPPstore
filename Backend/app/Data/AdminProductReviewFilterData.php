<?php

namespace App\Data;

use App\Enums\ProductReviewStatus;

final readonly class AdminProductReviewFilterData
{
    public function __construct(
        public ?string $search,
        public ?ProductReviewStatus $status,
        public ?int $rating,
        public ?bool $verified,
        public ?int $productId,
        public ?int $customerId,
        public ?string $fromDate,
        public ?string $toDate,
        public string $sort,
        public string $direction,
        public int $perPage = 20,
    ) {}

    /** @param array<string, mixed> $filters */
    public static function fromValidated(array $filters): self
    {
        $verified = null;
        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $verified = filter_var($filters['verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return new self(
            search: ! empty($filters['search']) ? (string) $filters['search'] : null,
            status: ! empty($filters['status']) ? ProductReviewStatus::tryFrom((string) $filters['status']) : null,
            rating: ! empty($filters['rating']) ? (int) $filters['rating'] : null,
            verified: $verified,
            productId: ! empty($filters['product_id']) ? (int) $filters['product_id'] : null,
            customerId: ! empty($filters['customer_id']) ? (int) $filters['customer_id'] : null,
            fromDate: ! empty($filters['from_date']) ? (string) $filters['from_date'] : null,
            toDate: ! empty($filters['to_date']) ? (string) $filters['to_date'] : null,
            sort: ! empty($filters['sort']) ? (string) $filters['sort'] : 'created_at',
            direction: strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            perPage: min(max((int) ($filters['per_page'] ?? 20), 5), 100),
        );
    }

    /** @param array<string, mixed> $filters */
    public static function fromArray(array $filters): self
    {
        return self::fromValidated($filters);
    }
}
