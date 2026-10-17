<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ReturnDeliveryToWarehouseData
{
    public function __construct(
        public ?int $receivingWarehouseId = null,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            receivingWarehouseId: isset($data['receiving_warehouse_id']) && filled($data['receiving_warehouse_id']) ? (int) $data['receiving_warehouse_id'] : null,
            notes: isset($data['notes']) && filled($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
