<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentChannel: string
{
    case Offline = 'offline';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'أوفلاين',
            self::Online => 'أونلاين',
        };
    }
}
