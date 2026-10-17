<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'أصول',
            self::Liability => 'التزامات',
            self::Equity => 'حقوق ملكية',
            self::Revenue => 'إيرادات',
            self::Expense => 'مصروفات',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Asset => 'bg-info-subtle text-info',
            self::Liability => 'bg-warning-subtle text-warning',
            self::Equity => 'bg-primary-subtle text-primary',
            self::Revenue => 'bg-success-subtle text-success',
            self::Expense => 'bg-danger-subtle text-danger',
        };
    }

    public function defaultNormalBalance(): AccountNormalBalance
    {
        return match ($this) {
            self::Asset, self::Expense => AccountNormalBalance::Debit,
            self::Liability, self::Equity, self::Revenue => AccountNormalBalance::Credit,
        };
    }
}
