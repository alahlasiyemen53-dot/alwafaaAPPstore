<?php

namespace App\Data;

final readonly class AdminWishlistFilterData
{
    public function __construct(
        public ?string $search,
        public ?int $customerId,
        public ?int $productId,
        public ?string $productStatus,
        public ?string $fromDate,
        public ?string $toDate,
        public string $sort,
        public string $direction,
        public int $perPage = 20,
    ) {}

    /** @param array<string, mixed> $filters */
    public static function fromValidated(array $filters): self
    {
        return new self(
            search: ! empty($filters['search']) ? (string) $filters['search'] : null,
            customerId: ! empty($filters['customer_id']) ? (int) $filters['customer_id'] : null,
            productId: ! empty($filters['product_id']) ? (int) $filters['product_id'] : null,
            productStatus: ! empty($filters['product_status']) ? (string) $filters['product_status'] : null,
            fromDate: ! empty($filters['from_date']) ? (string) $filters['from_date'] : null,
            toDate: ! empty($filters['to_date']) ? (string) $filters['to_date'] : null,
            sort: ! empty($filters['sort']) ? (string) $filters['sort'] : 'created_at',
            direction: strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            perPage: min(max((int) ($filters['per_page'] ?? 20), 5), 100),
        );
    }
}
