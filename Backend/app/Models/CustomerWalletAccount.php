<?php

namespace App\Models;

use App\Enums\CustomerWalletAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $dynamic_balance Transient ledger-derived balance attached for display only; not persisted.
 */
final class CustomerWalletAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'currency_id',
        'status',
    ];

    protected $casts = [
        'status' => CustomerWalletAccountStatus::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<CustomerWalletEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CustomerWalletEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === CustomerWalletAccountStatus::Active;
    }
}
