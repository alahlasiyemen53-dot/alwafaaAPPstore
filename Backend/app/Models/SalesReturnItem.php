<?php

namespace App\Models;

use App\Enums\ReturnReasonCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'order_item_id',
        'product_variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'sku_snapshot',
        'unit_id',
        'unit_name_snapshot',
        'return_quantity',
        'return_base_quantity',
        'unit_price_snapshot',
        'line_total',
        'base_currency_unit_price_snapshot',
        'base_currency_line_total',
        'reason_code',
        'notes',
    ];

    protected $casts = [
        'reason_code' => ReturnReasonCode::class,
        'return_quantity' => 'decimal:4',
        'return_base_quantity' => 'decimal:4',
        'unit_price_snapshot' => 'decimal:4',
        'line_total' => 'decimal:4',
        'base_currency_unit_price_snapshot' => 'decimal:4',
        'base_currency_line_total' => 'decimal:4',
    ];

    /** @return BelongsTo<SalesReturn, $this> */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
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

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
