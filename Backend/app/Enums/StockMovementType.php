<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PurchaseReceipt = 'purchase_receipt';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Damaged = 'damaged';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case DeliveryReturn = 'delivery_return';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseReceipt => 'استلام مشتريات',
            self::TransferOut => 'تحويل منصرف',
            self::TransferIn => 'تحويل وارد',
            self::AdjustmentIn => 'تسوية إضافة',
            self::AdjustmentOut => 'تسوية عجز',
            self::Damaged => 'شطب تالف',
            self::Sale => 'مبيعات منصرفة',
            self::SaleReturn => 'مرتجع مبيعات',
            self::DeliveryReturn => 'إرجاع توصيل للمستودع',
        };
    }

    public function defaultDirection(): StockMovementDirection
    {
        return match ($this) {
            self::PurchaseReceipt, self::TransferIn, self::AdjustmentIn, self::SaleReturn, self::DeliveryReturn => StockMovementDirection::In,
            self::TransferOut, self::AdjustmentOut, self::Damaged, self::Sale => StockMovementDirection::Out,
        };
    }
}
