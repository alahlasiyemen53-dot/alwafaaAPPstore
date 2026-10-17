<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = ['from_currency_id', 'to_currency_id', 'rate', 'effective_at'];

    protected $casts = ['rate' => 'decimal:8', 'effective_at' => 'datetime'];

    /** @return BelongsTo<Currency, $this> */
    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }
}
