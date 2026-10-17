<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';

    public function canTransitionTo(self $target): bool
    {
        return $target !== self::Draft && $target !== $this;
    }
}
