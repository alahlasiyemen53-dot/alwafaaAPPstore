<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case AssignedToDriver = 'assigned_to_driver';
    case AwaitingCustomerConfirmation = 'awaiting_customer_confirmation';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case ReturnedToWarehouse = 'returned_to_warehouse';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'قيد المراجعة',
            self::Confirmed => 'تم تأكيد الطلب',
            self::AssignedToDriver => 'جاهز للتوصيل',
            self::AwaitingCustomerConfirmation => 'تم تسليم الطلب من قبل المندوب',
            self::Delivered => 'مكتمل',
            self::DeliveryFailed => 'تعذر التوصيل (مع المندوب)',
            self::ReturnedToWarehouse => 'مرتجع للمستودع',
            self::Cancelled => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Confirmed => 'bg-success-subtle text-success border border-success-subtle',
            self::AssignedToDriver => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::AwaitingCustomerConfirmation => 'bg-info-subtle text-info border border-info-subtle',
            self::Delivered => 'bg-success-subtle text-success border border-success-subtle',
            self::DeliveryFailed => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::ReturnedToWarehouse => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::Cancelled => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }

    public function canBeEdited(): bool
    {
        return $this === self::Draft;
    }

    public function canBeConfirmed(): bool
    {
        return $this === self::Draft;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Draft || $this === self::Confirmed;
    }

    public function canBeAssignedToDriver(): bool
    {
        return $this === self::Confirmed;
    }

    public function canAwaitCustomerConfirmation(): bool
    {
        return $this === self::AssignedToDriver;
    }

    public function canBeCustomerConfirmed(): bool
    {
        return $this === self::AwaitingCustomerConfirmation;
    }
}
