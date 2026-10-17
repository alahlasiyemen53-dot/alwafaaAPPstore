<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SupportChatMessage extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_type',
        'sender_id',
        'type',
        'body',
        'attachment_path',
        'client_message_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function senderFirebaseUid(): string
    {
        return $this->sender_type.':'.$this->sender_id;
    }
}
