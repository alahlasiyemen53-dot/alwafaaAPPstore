<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportConversationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class SupportConversation extends Model
{
    protected $fillable = [
        'uuid',
        'customer_id',
        'assigned_admin_id',
        'related_order_id',
        'status',
        'last_message_at',
        'last_message_preview',
        'customer_last_read_at',
        'support_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportConversationStatus::class,
            'last_message_at' => 'datetime',
            'customer_last_read_at' => 'datetime',
            'support_last_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class, 'conversation_id');
    }

    public function customerFirebaseUid(): string
    {
        return 'customer:'.$this->customer_id;
    }
}
