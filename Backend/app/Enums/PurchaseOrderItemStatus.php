<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderItemStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'نشط',
            self::Cancelled => 'ملغى',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Pending;
    }
}
