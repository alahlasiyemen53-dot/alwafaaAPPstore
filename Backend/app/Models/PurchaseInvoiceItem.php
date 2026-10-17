<?php

namespace App\Models;

use App\Enums\PurchaseReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'free_quantity',
        'unit_cost',
        'line_subtotal',
        'discount_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'free_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<PurchaseReceiptItem, $this> */
    public function purchaseReceiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function getTotalReceivableQuantityAttribute(): float
    {
        return (float) $this->base_quantity + (float) $this->free_quantity;
    }

    public function getAlreadyReceivedQuantityAttribute(): float
    {
        return (float) $this->purchaseReceiptItems()
            ->whereHas('purchaseReceipt', fn ($q) => $q->where('status', PurchaseReceiptStatus::Posted))
            ->sum('received_base_quantity');
    }

    public function getRemainingReceivableQuantityAttribute(): float
    {
        return max(0, $this->total_receivable_quantity - $this->already_received_quantity);
    }
}
