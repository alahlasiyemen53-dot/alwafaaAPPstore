<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class PurchaseInvoiceData
{
    /**
     * @param  array<int, PurchaseInvoiceItemData>  $items
     */
    public function __construct(
        public ?string $invoiceNumber,
        public int $supplierId,
        public int $warehouseId,
        public int $currencyId,
        public ?string $supplierInvoiceNumber,
        public string $invoiceDate,
        public float $discountAmount,
        public float $otherCosts,
        public ?string $notes,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $items = [];
        if (! empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (is_array($item) && ! empty($item['product_variant_id']) && ! empty($item['unit_id'])) {
                    $items[] = PurchaseInvoiceItemData::fromArray($item);
                }
            }
        }

        return new self(
            invoiceNumber: isset($data['invoice_number']) && trim((string) $data['invoice_number']) !== '' ? trim((string) $data['invoice_number']) : null,
            supplierId: (int) $data['supplier_id'],
            warehouseId: (int) $data['warehouse_id'],
            currencyId: (int) $data['currency_id'],
            supplierInvoiceNumber: isset($data['supplier_invoice_number']) && trim((string) $data['supplier_invoice_number']) !== '' ? trim((string) $data['supplier_invoice_number']) : null,
            invoiceDate: self::normalizeDate((string) $data['invoice_date']),
            discountAmount: max(0.0, (float) ($data['discount_amount'] ?? 0)),
            otherCosts: max(0.0, (float) ($data['other_costs'] ?? 0)),
            notes: isset($data['notes']) ? trim((string) $data['notes']) : null,
            items: $items,
        );
    }

    private static function normalizeDate(string $date): string
    {
        $trimmed = trim($date);
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $trimmed)) {
            return Carbon::createFromFormat('d-m-Y', $trimmed)->format('Y-m-d');
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
            return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
        }

        return Carbon::parse($trimmed)->format('Y-m-d');
    }
}
