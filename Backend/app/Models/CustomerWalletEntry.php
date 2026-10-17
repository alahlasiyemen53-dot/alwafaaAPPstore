<?php

namespace App\Models;

use App\Enums\CustomerWalletEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CustomerWalletEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_wallet_account_id',
        'entry_number',
        'type',
        'amount',
        'currency_id',
        'source_type',
        'source_id',
        'source_event',
        'description',
        'journal_entry_id',
        'reversal_of_id',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'type' => CustomerWalletEntryType::class,
        'amount' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerWalletAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerWalletAccount::class, 'customer_wallet_account_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
