<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\ProviderFailureCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class BusinessNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'category',
        'channel',
        'title',
        'body',
        'payload_version',
        'business_reference',
        'idempotency_key',
        'scheduled_at',
        'read_at',
    ];

    protected $casts = [
        'type' => NotificationType::class,
        'category' => NotificationCategory::class,
        'channel' => NotificationChannel::class,
        'status' => NotificationStatus::class,
        'payload' => 'array',
        'payload_version' => 'integer',
        'queued_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'read_at' => 'datetime',
        'failure_category' => ProviderFailureCategory::class,
    ];

    /** @return MorphTo<Model, $this> */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
