<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ProductVariantFulfillmentMode;
use App\Models\Supplier;

final class VariantAvailabilityResult
{
    public function __construct(
        public readonly bool $isSellable,
        public readonly ProductVariantFulfillmentMode $fulfillmentMode,
        public readonly string $statusLabel,
        public readonly float $availableStock = 0.0,
        public readonly ?Supplier $supplier = null,
        public readonly ?string $reason = null,
    ) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'warehouseAvailableStock' => $this->availableStock,
            'supplierId' => $this->supplier?->id,
            'supplierName' => $this->supplier?->name,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['warehouseAvailableStock', 'supplierId', 'supplierName'], true);
    }

    public function toArray(): array
    {
        return [
            'is_sellable' => $this->isSellable,
            'fulfillment_mode' => $this->fulfillmentMode->value,
            'status_label' => $this->statusLabel,
            'available_stock' => $this->availableStock,
            'reason' => $this->reason,
        ];
    }
}
