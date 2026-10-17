<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    protected $fillable = ['country_id', 'name_ar', 'name_en', 'slug', 'is_active', 'is_delivery_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_delivery_active' => 'boolean',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /** @return HasMany<GovernorateDeliveryMethod, $this> */
    public function deliveryMethodAssignments(): HasMany
    {
        return $this->hasMany(GovernorateDeliveryMethod::class);
    }
}
