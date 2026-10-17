<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Collected = 'collected';
    case OnHold = 'on_hold';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الاستلام',
            self::Assigned => 'مسند للمندوب',
            self::Collected => 'تم الاستلام من المورد',
            self::OnHold => 'معلّق (صنف ناقص)',
            self::Failed => 'تعذر الاستلام',
            self::Cancelled => 'ملغى',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning',
            self::Assigned => 'bg-info-subtle text-info',
            self::Collected => 'bg-success-subtle text-success',
            self::OnHold => 'bg-warning-subtle text-warning',
            self::Failed => 'bg-danger-subtle text-danger',
            self::Cancelled => 'bg-secondary-subtle text-secondary',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Failed], true);
    }

    public function blocksDelivery(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::OnHold, self::Failed], true);
    }

    public function canChangeSupplier(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::OnHold, self::Failed], true);
    }

    public function canBeAssigned(): bool
    {
        return in_array($this, [self::Pending, self::Failed, self::OnHold], true);
    }

    public function canBeCollected(): bool
    {
        return in_array($this, [self::Pending, self::Assigned], true);
    }

    public function canBeHeld(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::Failed], true);
    }

    public function canBeFailed(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::OnHold], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::OnHold, self::Failed], true);
    }
}
