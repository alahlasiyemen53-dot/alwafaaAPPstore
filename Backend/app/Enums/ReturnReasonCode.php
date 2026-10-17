<?php

namespace App\Enums;

enum ReturnReasonCode: string
{
    case CustomerChangedMind = 'customer_changed_mind';
    case WrongItem = 'wrong_item';
    case DamagedItem = 'damaged_item';
    case DefectiveItem = 'defective_item';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerChangedMind => 'تراجع العميل عن الشراء',
            self::WrongItem => 'صنف غير مطابق للمطلوب',
            self::DamagedItem => 'صنف تالف أثناء النقل',
            self::DefectiveItem => 'عيب مصنعي في الصنف',
            self::Other => 'أسباب أخرى',
        };
    }
}
