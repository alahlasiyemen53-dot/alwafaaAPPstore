<?php

namespace App\Enums;

enum TreasuryTransactionType: string
{
    case CashReceipt = 'cash_receipt';
    case CashPayment = 'cash_payment';
    case BankDeposit = 'bank_deposit';
    case BankWithdrawal = 'bank_withdrawal';
    case WalletReceipt = 'wallet_receipt';
    case WalletPayment = 'wallet_payment';
    case Transfer = 'transfer';
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::CashReceipt => 'سند قبض نقدي',
            self::CashPayment => 'سند صرف نقدي',
            self::BankDeposit => 'إيداع بنكي',
            self::BankWithdrawal => 'سحب بنكي',
            self::WalletReceipt => 'قبض محفظة إلكترونية',
            self::WalletPayment => 'صرف محفظة إلكترونية',
            self::Transfer => 'تحويل بين الخزائن',
            self::OpeningBalance => 'رصيد افتتاحي',
            self::Adjustment => 'تسوية خزينة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CashReceipt, self::BankDeposit, self::WalletReceipt => 'badge bg-success-subtle text-success',
            self::CashPayment, self::BankWithdrawal, self::WalletPayment => 'badge bg-danger-subtle text-danger',
            self::Transfer => 'badge bg-primary-subtle text-primary',
            self::OpeningBalance => 'badge bg-info-subtle text-info',
            self::Adjustment => 'badge bg-warning-subtle text-warning',
        };
    }

    public function isIncoming(): bool
    {
        return match ($this) {
            self::CashReceipt, self::BankDeposit, self::WalletReceipt, self::OpeningBalance => true,
            default => false,
        };
    }

    public function isOutgoing(): bool
    {
        return match ($this) {
            self::CashPayment, self::BankWithdrawal, self::WalletPayment => true,
            default => false,
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }
}
