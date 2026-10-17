<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponDiscountType;
use App\Enums\CouponUsageStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CouponDiscountType $discount_type
 * @property float $discount_value
 * @property float|null $minimum_order_amount
 * @property float|null $maximum_discount_amount
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property bool $is_compatible_with_promotions
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'scope',
        'minimum_order_amount',
        'maximum_discount_amount',
        'starts_at',
        'ends_at',
        'is_active',
        'usage_limit',
        'per_customer_limit',
        'is_compatible_with_promotions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'discount_type' => CouponDiscountType::class,
        'discount_value' => 'decimal:4',
        'minimum_order_amount' => 'decimal:4',
        'maximum_discount_amount' => 'decimal:4',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'usage_limit' => 'integer',
        'per_customer_limit' => 'integer',
        'is_compatible_with_promotions' => 'boolean',
    ];

    /** @return HasMany<CouponTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(CouponTarget::class);
    }

    /** @return HasMany<CouponUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /** @return HasMany<CouponUsage, $this> */
    public function activeUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class)->where('status', CouponUsageStatus::Active->value);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isCurrentlyValid(?Carbon $time = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $checkTime = $time ?? now();

        if ($this->starts_at && $checkTime->isBefore($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $checkTime->isAfter($this->ends_at)) {
            return false;
        }

        return true;
    }
}
