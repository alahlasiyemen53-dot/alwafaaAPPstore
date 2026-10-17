<?php

declare(strict_types=1);

namespace App\Infrastructure\SupportChat;

use App\Contracts\SupportChat\SupportChatFirestoreGateway;
use App\Models\SupportChatMessage;
use App\Models\SupportConversation;

/** Used in tests and when Firebase credentials are unavailable. */
final class NullSupportChatFirestoreGateway implements SupportChatFirestoreGateway
{
    public function isReady(): bool
    {
        return false;
    }

    public function syncConversation(SupportConversation $conversation): void {}

    public function syncMessage(SupportConversation $conversation, SupportChatMessage $message): void {}
}
