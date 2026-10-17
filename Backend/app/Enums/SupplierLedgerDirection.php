<?php

namespace App\Enums;

enum SupplierLedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'دائن (لنا / التزام للمورد)',
            self::Debit => 'مدين (له / تسوية)',
        };
    }
}
