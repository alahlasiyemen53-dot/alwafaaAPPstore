<?php

namespace App\Models;

use App\Contracts\TreasuryAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string|null $derived_balance Transient GL-derived balance attached for display only; not persisted.
 */
final class Cashbox extends Model implements TreasuryAccount
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'currency_id',
        'gl_account_id',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    /** @return MorphMany<TreasuryTransaction, $this> */
    public function transactions(): MorphMany
    {
        return $this->morphMany(TreasuryTransaction::class, 'account');
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

    /** @return MorphMany<TreasuryCurrencyAccount, $this> */
    public function currencyAccounts(): MorphMany
    {
        return $this->morphMany(TreasuryCurrencyAccount::class, 'accountable');
    }

    protected static function booted(): void
    {
        self::created(function (self $cashbox): void {
            if ($cashbox->currency_id && $cashbox->gl_account_id) {
                $cashbox->currencyAccounts()->firstOrCreate(
                    ['currency_id' => $cashbox->currency_id],
                    [
                        'gl_account_id' => $cashbox->gl_account_id,
                        'is_active' => $cashbox->is_active,
                        'created_by' => $cashbox->created_by,
                        'updated_by' => $cashbox->updated_by,
                    ]
                );
            }
        });
    }

    /**
     * Authoritative balance derived from the General Ledger.
     */
    public function calculateBalance(?Carbon $asOf = null): string
    {
        return $this->glAccount?->calculateBalance($asOf) ?? '0.0000';
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name_ar} ({$this->code})";
    }
}
