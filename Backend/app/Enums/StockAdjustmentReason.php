<?php

namespace App\Enums;

enum StockAdjustmentReason: string
{
    case PhysicalCountGain = 'physical_count_gain';
    case PhysicalCountLoss = 'physical_count_loss';
    case Damaged = 'damaged';
    case DataCorrection = 'data_correction';

    public function label(): string
    {
        return match ($this) {
            self::PhysicalCountGain => 'فائض جردي',
            self::PhysicalCountLoss => 'عجز جردي',
            self::Damaged => 'بضاعة تالفة',
            self::DataCorrection => 'تصحيح بيانات',
        };
    }
}
