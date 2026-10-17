<?php

namespace App\Data;

final readonly class StockTransferData
{
    /**
     * @param  array<int, StockTransferItemData>  $items
     */
    public function __construct(
        public int $fromWarehouseId,
        public int $toWarehouseId,
        public ?string $notes = null,
        public array $items = [],
        public ?string $transferNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if ($item instanceof StockTransferItemData) {
                    $items[] = $item;
                } elseif (is_array($item)) {
                    $items[] = StockTransferItemData::fromArray($item);
                }
            }
        }

        return new self(
            fromWarehouseId: (int) $data['from_warehouse_id'],
            toWarehouseId: (int) $data['to_warehouse_id'],
            notes: $data['notes'] ?? null,
            items: $items,
            transferNumber: $data['transfer_number'] ?? null,
        );
    }
}
