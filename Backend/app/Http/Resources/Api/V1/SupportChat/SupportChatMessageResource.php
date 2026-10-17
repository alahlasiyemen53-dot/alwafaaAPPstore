<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\SupportChat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupportChatMessage */
final class SupportChatMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'sender_type' => $this->sender_type,
            'sender_uid' => $this->senderFirebaseUid(),
            'type' => $this->type,
            'text' => $this->body,
            'attachment_path' => $this->attachment_path,
            'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
