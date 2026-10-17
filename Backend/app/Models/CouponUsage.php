<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponUsageStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int $order_id
 * @property CouponUsageStatus $status
 * @property float $discount_amount_snapshot
 * @property int $currency_id
 * @property Carbon $used_at
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'status',
        'discount_amount_snapshot',
        'currency_id',
        'used_at',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'status' => CouponUsageStatus::class,
        'discount_amount_snapshot' => 'decimal:4',
        'used_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function isActive(): bool
    {
        return $this->status === CouponUsageStatus::Active;
    }
}
