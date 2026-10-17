<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'order_id',
        'supplier_id',
        'status',
        'assigned_driver_id',
        'assigned_at',
        'assigned_by',
        'collected_at',
        'collected_by',
        'on_hold_at',
        'on_hold_by',
        'on_hold_reason',
        'failed_at',
        'failed_by',
        'failure_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'assigned_at' => 'datetime',
        'collected_at' => 'datetime',
        'on_hold_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function activeItems(): HasMany
    {
        return $this->items()->where('status', 'pending');
    }

    /** @return BelongsTo<Driver, $this> */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /** @return HasOne<PurchaseInvoice, $this> */
    public function purchaseInvoice(): HasOne
    {
        return $this->hasOne(PurchaseInvoice::class);
    }

    public function isCollected(): bool
    {
        return $this->status === PurchaseOrderStatus::Collected;
    }

    public function isOnHold(): bool
    {
        return $this->status === PurchaseOrderStatus::OnHold;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderStatus::Cancelled;
    }

    public function canCollectFromSupplier(): bool
    {
        if (! $this->status->canBeCollected()) {
            return false;
        }

        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();

        return $order !== null && $order->pickup_driver_id !== null;
    }
}
