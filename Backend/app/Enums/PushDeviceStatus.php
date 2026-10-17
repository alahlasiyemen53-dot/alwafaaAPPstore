<?php

namespace App\Enums;

enum PushDeviceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invalidated = 'invalidated';
}
