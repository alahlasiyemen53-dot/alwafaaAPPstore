<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'address_type',
        'contact_name',
        'contact_phone',
        'country_id',
        'governorate_id',
        'city_id',
        'district_id',
        'governorate_name_snapshot',
        'city_name_snapshot',
        'district_name_snapshot',
        'country_name_snapshot',
        'country_code_snapshot',
        'neighborhood',
        'street',
        'building',
        'floor',
        'apartment',
        'landmark',
        'notes',
        'latitude',
        'longitude',
        'formatted_address',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Governorate, $this> */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function getFormattedAddressLineAttribute(): string
    {
        if (! empty($this->formatted_address)) {
            return $this->formatted_address;
        }

        $parts = array_filter([
            $this->governorate_name_snapshot,
            $this->city_name_snapshot,
            $this->district_name_snapshot,
            $this->neighborhood,
            $this->street,
            $this->building ? 'مبنى '.$this->building : null,
            $this->floor ? 'الدور '.$this->floor : null,
            $this->apartment ? 'شقة '.$this->apartment : null,
            $this->landmark ? 'بجوار '.$this->landmark : null,
        ]);

        return implode(' - ', $parts);
    }
}
