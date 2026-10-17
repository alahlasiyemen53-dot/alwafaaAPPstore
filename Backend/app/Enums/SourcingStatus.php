<?php

declare(strict_types=1);

namespace App\Enums;

enum SourcingStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Collected = 'collected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الإسناد',
            self::Assigned => 'مسندة للمندوب',
            self::Collected => 'تم الاستلام من المورد',
            self::Failed => 'تعذر الاستلام',
            self::Cancelled => 'ملغية',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning',
            self::Assigned => 'bg-info-subtle text-info',
            self::Collected => 'bg-success-subtle text-success',
            self::Failed => 'bg-danger-subtle text-danger',
            self::Cancelled => 'bg-secondary-subtle text-secondary',
        };
    }

    public function canBeAssigned(): bool
    {
        return $this === self::Pending || $this === self::Failed;
    }

    public function canBeCollected(): bool
    {
        return $this === self::Assigned || $this === self::Pending;
    }

    public function canBeFailed(): bool
    {
        return $this === self::Assigned || $this === self::Pending;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Pending || $this === self::Assigned;
    }
}
