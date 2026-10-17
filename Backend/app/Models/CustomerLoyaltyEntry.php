<?php

namespace App\Models;

use App\Enums\LoyaltyEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CustomerLoyaltyEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_loyalty_account_id',
        'entry_number',
        'type',
        'points',
        'source_type',
        'source_id',
        'source_event',
        'description',
        'occurred_at',
        'expires_at',
        'reversal_of_id',
        'created_by',
    ];

    protected $casts = [
        'type' => LoyaltyEntryType::class,
        'points' => 'integer',
        'occurred_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerLoyaltyAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyAccount::class, 'customer_loyalty_account_id');
    }

    /** @return BelongsTo<self, $this> */
    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<self, $this> */
    public function reversalEntry(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
