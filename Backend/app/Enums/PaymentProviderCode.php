<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentProviderCode: string
{
    case Cod = 'cod';
    case Jaib = 'jaib';
    case Floosak = 'floosak';
}
