<?php

namespace App\Data;

final readonly class PurchaseReceiptItemData
{
    public function __construct(
        public int $purchaseInvoiceItemId,
        public int $productVariantId,
        public float $receivedBaseQuantity,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            purchaseInvoiceItemId: (int) $data['purchase_invoice_item_id'],
            productVariantId: (int) $data['product_variant_id'],
            receivedBaseQuantity: (float) $data['received_base_quantity'],
            notes: $data['notes'] ?? null,
        );
    }
}
