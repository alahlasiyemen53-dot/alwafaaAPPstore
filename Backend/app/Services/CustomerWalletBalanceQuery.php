<?php

namespace App\Services;

use App\Enums\CustomerWalletEntryType;
use App\Models\CustomerWalletAccount;
use App\Support\Decimal;

final class CustomerWalletBalanceQuery
{
    public function getBalance(CustomerWalletAccount $account): string
    {
        $creditTypes = [
            CustomerWalletEntryType::RefundCredit->value,
            CustomerWalletEntryType::ManualCredit->value,
        ];

        $debitTypes = [
            CustomerWalletEntryType::ManualDebit->value,
        ];

        $credits = '0.0000';
        $debits = '0.0000';

        $entries = $account->entries()->with('originalEntry')->get();

        foreach ($entries as $entry) {
            $amt = Decimal::normalize($entry->amount);

            if (in_array($entry->type->value, $creditTypes, true)) {
                $credits = Decimal::add($credits, $amt);
            } elseif (in_array($entry->type->value, $debitTypes, true)) {
                $debits = Decimal::add($debits, $amt);
            } elseif ($entry->type === CustomerWalletEntryType::Reversal && $entry->originalEntry) {
                // If the original entry was a credit, reversing it acts as a debit
                if ($entry->originalEntry->type->isCredit()) {
                    $debits = Decimal::add($debits, $amt);
                } else {
                    $credits = Decimal::add($credits, $amt);
                }
            }
        }

        return Decimal::sub($credits, $debits);
    }
}
