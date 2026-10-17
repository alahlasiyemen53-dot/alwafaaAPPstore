<?php

namespace App\Models;

use App\Contracts\TreasuryAccount;
use App\Enums\DriverCodSettlementStatus;
use App\Enums\TreasuryTransactionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * Represents an independent currency sub-account for a Treasury Entity
 * (Cashbox, BankAccount, or ElectronicWalletAccount).
 *
 * @property int $id
 * @property string $accountable_type
 * @property int $accountable_id
 * @property int $currency_id
 * @property int $gl_account_id
 * @property bool $is_active
 * @property string|null $notes
 * @property string|null $derived_balance
 */
final class TreasuryCurrencyAccount extends Model implements TreasuryAccount
{
    use HasFactory;

    protected $table = 'treasury_currency_accounts';

    protected $fillable = [
        'accountable_type',
        'accountable_id',
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

    /**
     * Parent Treasury entity (Cashbox, BankAccount, or ElectronicWalletAccount).
     *
     * @return MorphTo<Model, $this>
     */
    public function accountable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The currency of this sub-account.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * The linked General Ledger Asset account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    /**
     * Treasury transactions posted directly against this currency account.
     *
     * @return HasMany<TreasuryTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'treasury_currency_account_id');
    }

    /**
     * COD settlements paid into this currency account.
     *
     * @return HasMany<DriverCodSettlement, $this>
     */
    public function codSettlements(): HasMany
    {
        return $this->hasMany(DriverCodSettlement::class, 'treasury_currency_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Authoritative ledger-derived balance for this currency account.
     */
    public function calculateBalance(?Carbon $asOf = null): string
    {
        return app(\App\Services\TreasuryBalanceService::class)->getAuthoritativeBalance($this, $asOf);
    }

    /**
     * Human-friendly display label with currency code.
     */
    public function getDisplayNameAttribute(): string
    {
        $parent = $this->accountable;
        $parentName = match (true) {
            $parent instanceof Cashbox => $parent->name_ar,
            $parent instanceof BankAccount => "{$parent->bank_name} - {$parent->account_name}",
            $parent instanceof ElectronicWalletAccount => "{$parent->provider_name} - {$parent->account_label}",
            default => 'حساب خزينة',
        };

        $currCode = $this->currency->code ?? '';

        return "{$parentName} [{$currCode}]";
    }

    /**
     * Check if this currency account has any posted financial history.
     */
    public function hasPostedHistory(): bool
    {
        $hasPostedTrx = $this->transactions()
            ->where('status', TreasuryTransactionStatus::Posted)
            ->exists();

        if ($hasPostedTrx) {
            return true;
        }

        $hasPostedCod = $this->codSettlements()
            ->where('status', DriverCodSettlementStatus::Posted)
            ->exists();

        if ($hasPostedCod) {
            return true;
        }

        if (! $this->gl_account_id) {
            return false;
        }

        $query = DB::table('journal_entry_lines')
            ->where('account_id', $this->gl_account_id)
            ->where('transaction_currency_id', $this->currency_id);

        $hasStamped = DB::table('journal_entry_lines')
            ->where('treasury_currency_account_id', $this->id)
            ->exists();

        if ($hasStamped) {
            return $query->where('treasury_currency_account_id', $this->id)->exists();
        }

        return $query->whereNull('treasury_currency_account_id')->exists();
    }
}
