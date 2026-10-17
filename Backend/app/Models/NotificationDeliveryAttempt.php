<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationDeliveryAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_delivery_id',
        'attempt_number',
        'status',
        'failure_category',
        'provider_reference',
        'failure_summary',
        'attempted_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'status' => NotificationStatus::class,
        'failure_category' => ProviderFailureCategory::class,
        'attempted_at' => 'datetime',
    ];

    /** @return BelongsTo<NotificationDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'notification_delivery_id');
    }
}
