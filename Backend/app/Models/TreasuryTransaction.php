<?php

namespace App\Models;

use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class TreasuryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'transaction_type',
        'status',
        'account_type',
        'account_id',
        'treasury_currency_account_id',
        'destination_type',
        'destination_id',
        'destination_currency_account_id',
        'currency_id',
        'amount',
        'exchange_rate_snapshot',
        'base_currency_amount',
        'offset_account_id',
        'counterparty_type',
        'counterparty_id',
        'reference_number',
        'description',
        'journal_entry_id',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transaction_type' => TreasuryTransactionType::class,
        'status' => TreasuryTransactionStatus::class,
        'amount' => 'decimal:4',
        'exchange_rate_snapshot' => 'decimal:8',
        'base_currency_amount' => 'decimal:4',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function account(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<TreasuryCurrencyAccount, $this> */
    public function currencyAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryCurrencyAccount::class, 'treasury_currency_account_id');
    }

    public function destinationAccount(): MorphTo
    {
        return $this->morphTo('destination');
    }

    /** @return BelongsTo<TreasuryCurrencyAccount, $this> */
    public function destinationCurrencyAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryCurrencyAccount::class, 'destination_currency_account_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function offsetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'offset_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Reversal journal created for this transaction's posted entry (via reversal_of_id).
     *
     * @return HasOne<JournalEntry, $this>
     */
    public function reversalJournalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reversal_of_id', 'journal_entry_id');
    }

    public function counterparty(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === TreasuryTransactionStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === TreasuryTransactionStatus::Posted;
    }

    public function isReversed(): bool
    {
        return $this->status === TreasuryTransactionStatus::Reversed;
    }

    public function isTransfer(): bool
    {
        return $this->transaction_type === TreasuryTransactionType::Transfer;
    }
}
