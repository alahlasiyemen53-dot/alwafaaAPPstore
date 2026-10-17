<?php

namespace App\Data;

final readonly class PurchaseReceiptData
{
    /**
     * @param  array<int, PurchaseReceiptItemData>  $items
     */
    public function __construct(
        public int $purchaseInvoiceId,
        public int $warehouseId,
        public ?string $notes = null,
        public array $items = [],
        public ?string $receiptNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if ($item instanceof PurchaseReceiptItemData) {
                    $items[] = $item;
                } elseif (is_array($item)) {
                    $items[] = PurchaseReceiptItemData::fromArray($item);
                }
            }
        }

        return new self(
            purchaseInvoiceId: (int) $data['purchase_invoice_id'],
            warehouseId: (int) $data['warehouse_id'],
            notes: $data['notes'] ?? null,
            items: $items,
            receiptNumber: $data['receipt_number'] ?? null,
        );
    }
}
