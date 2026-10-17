<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'driver_code',
        'name',
        'phone',
        'normalized_phone',
        'secondary_phone',
        'vehicle_type',
        'vehicle_plate_number',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        self::saving(function (Driver $driver): void {
            if (empty($driver->normalized_phone) && ! empty($driver->phone)) {
                $driver->normalized_phone = $driver->phone;
            }
            if (empty($driver->code) && ! empty($driver->attributes['driver_code'])) {
                $driver->code = $driver->attributes['driver_code'];
            }
        });
    }

    public function getDriverCodeAttribute(): string
    {
        return (string) ($this->attributes['code'] ?? $this->attributes['driver_code'] ?? '');
    }

    public function setDriverCodeAttribute($value): void
    {
        $this->attributes['code'] = $value;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->secondary_phone;
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['secondary_phone'] = $value;
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function activeDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('status', 'assigned');
    }

    /** @return HasMany<DriverCodSettlement, $this> */
    public function codSettlements(): HasMany
    {
        return $this->hasMany(DriverCodSettlement::class);
    }

    /** @return HasMany<OrderSourcingRequirement, $this> */
    public function sourcingRequirements(): HasMany
    {
        return $this->hasMany(OrderSourcingRequirement::class, 'assigned_driver_id');
    }
}
