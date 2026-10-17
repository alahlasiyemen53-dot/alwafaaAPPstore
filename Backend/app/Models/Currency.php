<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'symbol', 'decimal_places', 'is_default', 'is_active'];

    protected $casts = ['decimal_places' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];

    /** @return HasMany<ExchangeRate, $this> */
    public function outgoingRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function incomingRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    /** @return HasMany<VariantPrice, $this> */
    public function variantPrices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }
}
