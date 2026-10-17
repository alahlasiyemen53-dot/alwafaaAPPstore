<?php

namespace App\Data;

final readonly class StockAdjustmentData
{
    /**
     * @param  array<int, StockAdjustmentItemData>  $items
     */
    public function __construct(
        public int $warehouseId,
        public string $reasonCode,
        public ?string $notes = null,
        public array $items = [],
        public ?string $adjustmentNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if ($item instanceof StockAdjustmentItemData) {
                    $items[] = $item;
                } elseif (is_array($item)) {
                    $items[] = StockAdjustmentItemData::fromArray($item);
                }
            }
        }

        return new self(
            warehouseId: (int) $data['warehouse_id'],
            reasonCode: (string) $data['reason_code'],
            notes: $data['notes'] ?? null,
            items: $items,
            adjustmentNumber: $data['adjustment_number'] ?? null,
        );
    }
}
