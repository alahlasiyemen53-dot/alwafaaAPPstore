<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DriverCodSettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'delivery_id',
        'order_id',
        'collected_amount',
        'previously_settled_amount',
        'settlement_amount',
        'currency_id',
    ];

    protected $casts = [
        'collected_amount' => 'decimal:4',
        'previously_settled_amount' => 'decimal:4',
        'settlement_amount' => 'decimal:4',
    ];

    /** @return BelongsTo<DriverCodSettlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(DriverCodSettlement::class, 'settlement_id');
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function getRemainingAmountAttribute(): float
    {
        $remaining = (float) $this->collected_amount - (float) $this->previously_settled_amount - (float) $this->settlement_amount;

        return max(0.0, round($remaining, 4));
    }
}
