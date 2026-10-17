<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_number',
        'order_id',
        'driver_id',
        'status',
        'assigned_at',
        'assigned_by',
        'delivered_at',
        'delivered_by',
        'failed_at',
        'failed_by',
        'failure_reason_code',
        'failure_notes',
        'returned_at',
        'returned_by',
        'receiving_warehouse_id',
        'cod_amount_to_collect',
        'cod_collected_amount',
        'cod_currency_id',
        'notes',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'failure_reason_code' => DeliveryFailureReason::class,
        'assigned_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'returned_at' => 'datetime',
        'cod_amount_to_collect' => 'decimal:4',
        'cod_collected_amount' => 'decimal:4',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /** @return BelongsTo<User, $this> */
    public function failedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'failed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function receivingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function codCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'cod_currency_id');
    }

    public function getCodAmountAttribute(): float
    {
        return (float) ($this->cod_amount_to_collect ?? 0);
    }

    public function getRecipientNameAttribute(): string
    {
        return (string) ($this->order->customer_name_snapshot ?? $this->order->deliveryAddress->contact_name ?? '');
    }

    public function getRecipientPhoneAttribute(): string
    {
        return (string) ($this->order->customer_phone_snapshot ?? $this->order->deliveryAddress->contact_phone ?? '');
    }

    public function getShippingAddressSnapshotAttribute(): string
    {
        return (string) ($this->order->deliveryAddress->formatted_address_line ?? '');
    }

    public function getFailureReasonAttribute(): ?DeliveryFailureReason
    {
        return $this->failure_reason_code;
    }

    public function canBeDelivered(): bool
    {
        return $this->status->canBeDelivered();
    }

    public function canBeMarkedFailed(): bool
    {
        return $this->status->canBeMarkedFailed();
    }

    public function canBeReturnedToWarehouse(): bool
    {
        return $this->status->canBeReturnedToWarehouse();
    }

    /** @return HasMany<DriverCodSettlementItem, $this> */
    public function settlementItems(): HasMany
    {
        return $this->hasMany(DriverCodSettlementItem::class);
    }

    public function getSettledCodAmountAttribute(): float
    {
        return (float) $this->settlementItems()
            ->whereHas('settlement', fn ($q) => $q->where('status', DriverCodSettlementStatus::Posted))
            ->sum('settlement_amount');
    }

    public function getOutstandingCodAmountAttribute(): float
    {
        if (! $this->isEligibleForCodSettlement()) {
            return 0.0;
        }

        $collected = (float) ($this->cod_collected_amount ?? 0);
        $settled = $this->settled_cod_amount;

        return max(0.0, round($collected - $settled, 4));
    }

    public function isEligibleForCodSettlement(): bool
    {
        return $this->status === DeliveryStatus::Delivered
            && (float) ($this->cod_collected_amount ?? 0) > 0;
    }
}
