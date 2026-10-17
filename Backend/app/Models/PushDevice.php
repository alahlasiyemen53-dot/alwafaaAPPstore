<?php

namespace App\Models;

use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class PushDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_identifier',
        'platform',
        'locale',
        'app_version',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'platform' => PushDevicePlatform::class,
        'status' => PushDeviceStatus::class,
        'last_registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * Human-readable label for admin UI (no dedicated device_name column).
     */
    public function getDisplayLabelAttribute(): string
    {
        $platform = $this->platform?->value ?? 'unknown';
        $identifier = filled($this->device_identifier)
            ? (string) $this->device_identifier
            : '#'.$this->id;
        $suffix = filled($this->app_version) ? ' v'.$this->app_version : '';

        return strtoupper($platform).' '.$identifier.$suffix;
    }
}
