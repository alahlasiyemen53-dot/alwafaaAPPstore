<?php

namespace App\Enums;

enum StockMovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'وارد',
            self::Out => 'منصرف',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::In => 'bg-success-subtle text-success border border-success-subtle',
            self::Out => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }
}
