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
final class ElectronicWalletAccount extends Model implements TreasuryAccount
{
    use HasFactory;

    protected $fillable = [
        'code',
        'provider_name',
        'account_label',
        'account_identifier',
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
        self::created(function (self $wallet): void {
            if ($wallet->currency_id && $wallet->gl_account_id) {
                $wallet->currencyAccounts()->firstOrCreate(
                    ['currency_id' => $wallet->currency_id],
                    [
                        'gl_account_id' => $wallet->gl_account_id,
                        'is_active' => $wallet->is_active,
                        'created_by' => $wallet->created_by,
                        'updated_by' => $wallet->updated_by,
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
        return "{$this->provider_name} - {$this->account_label} ({$this->code})";
    }
}
