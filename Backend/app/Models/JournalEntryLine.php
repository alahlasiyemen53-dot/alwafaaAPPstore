<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'treasury_currency_account_id',
        'description',
        'debit',
        'credit',
        'transaction_currency_id',
        'transaction_amount',
        'exchange_rate_snapshot',
        'base_currency_amount',
        'party_type',
        'party_id',
        'line_order',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'transaction_amount' => 'decimal:4',
        'exchange_rate_snapshot' => 'decimal:8',
        'base_currency_amount' => 'decimal:4',
        'line_order' => 'integer',
    ];

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<TreasuryCurrencyAccount, $this> */
    public function treasuryCurrencyAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryCurrencyAccount::class, 'treasury_currency_account_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function transactionCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'transaction_currency_id');
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }
}
