<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'محجوز (نشط)',
            self::Released => 'محرر (ملغي)',
            self::Consumed => 'مستهلك (خرج للتسليم)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-info-subtle text-info border border-info-subtle',
            self::Released => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::Consumed => 'bg-success-subtle text-success border border-success-subtle',
        };
    }
}
