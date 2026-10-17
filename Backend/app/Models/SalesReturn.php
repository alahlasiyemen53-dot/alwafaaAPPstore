<?php

namespace App\Models;

use App\Enums\ReturnReasonCode;
use App\Enums\SalesReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class SalesReturn extends Model
{
    protected $fillable = [
        'return_number',
        'order_id',
        'warehouse_id',
        'status',
        'reason_code',
        'notes',
        'currency_id',
        'exchange_rate_snapshot',
        'subtotal',
        'total',
        'base_currency_total',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => SalesReturnStatus::class,
        'reason_code' => ReturnReasonCode::class,
        'exchange_rate_snapshot' => 'decimal:8',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
        'base_currency_total' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<SalesReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return MorphMany<StockMovement, $this> */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function canBeEdited(): bool
    {
        return $this->status === SalesReturnStatus::Draft;
    }

    public function canBePosted(): bool
    {
        return $this->status === SalesReturnStatus::Draft;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === SalesReturnStatus::Draft;
    }
}
