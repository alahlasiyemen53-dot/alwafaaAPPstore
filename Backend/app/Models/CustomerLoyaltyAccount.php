<?php

namespace App\Models;

use App\Enums\LoyaltyAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $dynamic_balance Transient ledger-derived available points attached for display only; not persisted.
 */
final class CustomerLoyaltyAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
    ];

    protected $casts = [
        'status' => LoyaltyAccountStatus::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CustomerLoyaltyEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyEntry::class);
    }

    /** @return HasMany<CustomerLoyaltyReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyReservation::class);
    }

    public function isActive(): bool
    {
        return $this->status === LoyaltyAccountStatus::Active;
    }
}
