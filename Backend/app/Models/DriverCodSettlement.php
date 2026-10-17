<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverCodSettlementStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $settlement_number
 * @property int $driver_id
 * @property int $currency_id
 * @property DriverCodSettlementStatus|string $status
 * @property string $treasury_account_type
 * @property int $treasury_account_id
 * @property float $total_expected
 * @property float $total_settled
 * @property int|null $journal_entry_id
 * @property Carbon|null $posted_at
 * @property Carbon|null $reversed_at
 */
final class DriverCodSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_number',
        'driver_id',
        'currency_id',
        'status',
        'treasury_account_type',
        'treasury_account_id',
        'treasury_currency_account_id',
        'total_expected',
        'total_settled',
        'notes',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'journal_entry_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => DriverCodSettlementStatus::class,
        'total_expected' => 'decimal:4',
        'total_settled' => 'decimal:4',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function treasuryAccount(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'treasury_account_type', 'treasury_account_id');
    }

    /** @return BelongsTo<TreasuryCurrencyAccount, $this> */
    public function treasuryCurrencyAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryCurrencyAccount::class, 'treasury_currency_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return HasMany<DriverCodSettlementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DriverCodSettlementItem::class, 'settlement_id');
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        $val = $this->status instanceof DriverCodSettlementStatus ? $this->status->value : (string) $this->status;

        return $val === DriverCodSettlementStatus::Draft->value;
    }

    public function isPosted(): bool
    {
        $val = $this->status instanceof DriverCodSettlementStatus ? $this->status->value : (string) $this->status;

        return $val === DriverCodSettlementStatus::Posted->value;
    }

    public function isReversed(): bool
    {
        $val = $this->status instanceof DriverCodSettlementStatus ? $this->status->value : (string) $this->status;

        return $val === DriverCodSettlementStatus::Reversed->value;
    }

    public function canBePosted(): bool
    {
        return $this->isDraft() && $this->total_settled > 0;
    }

    public function canBeReversed(): bool
    {
        return $this->isPosted() && $this->reversed_at === null;
    }
}
