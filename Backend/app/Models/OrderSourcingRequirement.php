<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourcingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderSourcingRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_variant_id',
        'supplier_id',
        'quantity',
        'status',
        'assigned_driver_id',
        'assigned_at',
        'assigned_by',
        'collected_at',
        'collected_by',
        'failed_at',
        'failed_by',
        'failure_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'status' => SourcingStatus::class,
        'assigned_at' => 'datetime',
        'collected_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

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

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->assignedDriver();
    }

    public function isPending(): bool
    {
        return $this->status === SourcingStatus::Pending;
    }

    public function isAssigned(): bool
    {
        return $this->status === SourcingStatus::Assigned;
    }

    public function isCollected(): bool
    {
        return $this->status === SourcingStatus::Collected;
    }

    public function isFailed(): bool
    {
        return $this->status === SourcingStatus::Failed;
    }

    public function isCancelled(): bool
    {
        return $this->status === SourcingStatus::Cancelled;
    }

    public function getQuantityRequestedAttribute(): float
    {
        return (float) ($this->attributes['quantity'] ?? 0);
    }

    public function getDriverIdAttribute(): ?int
    {
        return isset($this->attributes['assigned_driver_id']) ? (int) $this->attributes['assigned_driver_id'] : null;
    }

    /** @return BelongsTo<User, $this> */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function collectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function failedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'failed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
