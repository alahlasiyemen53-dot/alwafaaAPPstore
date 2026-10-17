<?php

declare(strict_types=1);

namespace App\Contracts\SupportChat;

use App\Models\SupportChatMessage;
use App\Models\SupportConversation;

interface SupportChatFirestoreGateway
{
    public function isReady(): bool;

    public function syncConversation(SupportConversation $conversation): void;

    public function syncMessage(SupportConversation $conversation, SupportChatMessage $message): void;
}
