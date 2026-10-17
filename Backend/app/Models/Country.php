<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'calling_code',
        'is_active',
        'is_delivery_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_delivery_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** ISO country code (YE). Stored in `code` column for reuse of existing schema. */
    public function getIsoCodeAttribute(): string
    {
        return (string) $this->code;
    }

    /** @return HasMany<Governorate, $this> */
    public function governorates(): HasMany
    {
        return $this->hasMany(Governorate::class);
    }

    public static function defaultActive(): ?self
    {
        return static::query()
            ->where('code', 'YE')
            ->where('is_active', true)
            ->first()
            ?? static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
    }

    public static function defaultDeliveryActive(): ?self
    {
        return static::query()
            ->where('code', 'YE')
            ->where('is_active', true)
            ->where('is_delivery_active', true)
            ->first()
            ?? static::query()
                ->where('is_active', true)
                ->where('is_delivery_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
    }
}
