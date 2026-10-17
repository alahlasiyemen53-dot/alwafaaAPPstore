<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\SupportChat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupportConversation */
final class SupportConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'related_order_id' => $this->related_order_id,
            'assigned_admin_id' => $this->assigned_admin_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_preview' => $this->last_message_preview,
            'customer_last_read_at' => $this->customer_last_read_at?->toIso8601String(),
            'support_last_read_at' => $this->support_last_read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
