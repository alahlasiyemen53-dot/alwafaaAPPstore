<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Assigned = 'assigned';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case ReturnedToWarehouse = 'returned_to_warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'مسند للمندوب (قيد التوصيل)',
            self::Delivered => 'تم التسليم للعميل',
            self::Failed => 'تعذر التسليم (مع المندوب)',
            self::ReturnedToWarehouse => 'تم الإرجاع للمستودع',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Assigned => 'badge bg-primary-subtle text-primary border border-primary-subtle',
            self::Delivered => 'badge bg-success-subtle text-success border border-success-subtle',
            self::Failed => 'badge bg-danger-subtle text-danger border border-danger-subtle',
            self::ReturnedToWarehouse => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function canBeDelivered(): bool
    {
        return $this === self::Assigned;
    }

    public function canBeMarkedFailed(): bool
    {
        return $this === self::Assigned;
    }

    public function canBeReturnedToWarehouse(): bool
    {
        return $this === self::Failed;
    }
}
