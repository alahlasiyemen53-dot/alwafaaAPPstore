<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_notification_id',
        'push_device_id',
        'channel',
        'provider',
        'status',
        'retryable',
        'provider_reference',
        'failure_category',
        'failure_summary',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => NotificationStatus::class,
        'retryable' => 'boolean',
        'failure_category' => ProviderFailureCategory::class,
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /** @return BelongsTo<BusinessNotification, $this> */
    public function businessNotification(): BelongsTo
    {
        return $this->belongsTo(BusinessNotification::class);
    }

    /** @return BelongsTo<PushDevice, $this> */
    public function pushDevice(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class);
    }

    /** @return HasMany<NotificationDeliveryAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationDeliveryAttempt::class);
    }
}
