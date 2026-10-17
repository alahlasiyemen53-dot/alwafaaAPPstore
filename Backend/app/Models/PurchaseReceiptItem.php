<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseReceiptItem extends Model
{
    protected $fillable = [
        'purchase_receipt_id',
        'purchase_invoice_item_id',
        'product_variant_id',
        'received_base_quantity',
        'unit_cost_snapshot',
        'currency_id',
        'exchange_rate_snapshot',
        'base_currency_cost',
        'notes',
    ];

    protected $casts = [
        'received_base_quantity' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:4',
        'exchange_rate_snapshot' => 'decimal:8',
        'base_currency_cost' => 'decimal:4',
    ];

    /** @return BelongsTo<PurchaseReceipt, $this> */
    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    /** @return BelongsTo<PurchaseInvoiceItem, $this> */
    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
