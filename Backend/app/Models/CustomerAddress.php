<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'country_id',
        'label',
        'contact_name',
        'contact_phone',
        'governorate_id',
        'city_id',
        'district_id',
        'neighborhood',
        'street',
        'building',
        'floor',
        'apartment',
        'landmark',
        'notes',
        'latitude',
        'longitude',
        'google_place_id',
        'formatted_address',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function fullAddressText(): string
    {
        if (! empty($this->formatted_address)) {
            return (string) $this->formatted_address;
        }

        $countryName = $this->relationLoaded('country') ? $this->country?->name_ar : null;
        $governorateName = $this->relationLoaded('governorate') ? $this->governorate?->name_ar : null;
        $cityName = $this->relationLoaded('city') ? $this->city?->name_ar : null;
        $districtName = $this->relationLoaded('district') ? $this->district?->name_ar : null;

        $parts = array_filter([
            $countryName,
            $governorateName,
            $cityName,
            $districtName,
            $this->neighborhood,
            $this->street,
            $this->building ? 'مبنى '.$this->building : null,
            $this->floor ? 'طابق '.$this->floor : null,
            $this->apartment ? 'شقة '.$this->apartment : null,
            $this->landmark ? 'بجوار '.$this->landmark : null,
        ]);

        return implode(' - ', $parts);
    }
}
