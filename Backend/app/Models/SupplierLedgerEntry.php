<?php

namespace App\Models;

use App\Enums\SupplierLedgerDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $balance Transient aggregate column populated only by grouped balance queries; not a real database column.
 */
final class SupplierLedgerEntry extends Model
{
    protected $fillable = [
        'supplier_id',
        'currency_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'amount',
        'direction',
        'exchange_rate_snapshot',
        'base_currency_amount',
        'description',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'direction' => SupplierLedgerDirection::class,
        'exchange_rate_snapshot' => 'decimal:8',
        'base_currency_amount' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
