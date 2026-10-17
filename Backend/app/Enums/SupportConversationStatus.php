<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportConversationStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Closed = 'closed';

    public function allowsCustomerMessage(): bool
    {
        return $this !== self::Closed;
    }

    public function allowsSupportMessage(): bool
    {
        return $this !== self::Closed;
    }
}
