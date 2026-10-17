<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductVariantFulfillmentMode: string
{
    case Stocked = 'stocked';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return match ($this) {
            self::Stocked => 'مخزني',
            self::OnDemand => 'توفير عند الطلب',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Stocked => 'bg-info-subtle text-info',
            self::OnDemand => 'bg-warning-subtle text-warning',
        };
    }
}
