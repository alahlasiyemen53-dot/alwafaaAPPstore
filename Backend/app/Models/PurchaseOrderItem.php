<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'order_item_id',
        'product_variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'sku_snapshot',
        'supplier_item_number_snapshot',
        'quantity',
        'cost_price_snapshot',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'cost_price_snapshot' => 'decimal:4',
        'status' => PurchaseOrderItemStatus::class,
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function isActive(): bool
    {
        return $this->status === PurchaseOrderItemStatus::Pending;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderItemStatus::Cancelled;
    }
}
