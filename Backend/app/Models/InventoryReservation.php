<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'order_id',
        'order_item_id',
        'quantity',
        'status',
        'reserved_at',
        'released_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'status' => ReservationStatus::class,
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Active);
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Released);
    }

    public function scopeConsumed(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Consumed);
    }
}
