<?php

namespace App\Services;

use App\Enums\CustomerWalletEntryType;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Support\Decimal;

final class CustomerWalletStatementQuery
{
    /**
     * @return array{
     *     account: CustomerWalletAccount,
     *     opening_balance: string,
     *     closing_balance: string,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function getStatement(CustomerWalletAccount $account, ?string $startDate = null, ?string $endDate = null): array
    {
        $allEntries = CustomerWalletEntry::query()
            ->where('customer_wallet_account_id', $account->id)
            ->with(['originalEntry', 'journalEntry', 'creator'])
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get();

        $openingBalance = '0.0000';
        $runningBalance = '0.0000';
        $statementEntries = [];

        foreach ($allEntries as $entry) {
            $postedAtDate = $entry->posted_at->toDateString();
            $amt = Decimal::normalize($entry->amount);

            $isCredit = false;
            $isDebit = false;

            if ($entry->type === CustomerWalletEntryType::ManualCredit || $entry->type === CustomerWalletEntryType::RefundCredit) {
                $isCredit = true;
            } elseif ($entry->type === CustomerWalletEntryType::ManualDebit) {
                $isDebit = true;
            } elseif ($entry->originalEntry) {
                if ($entry->originalEntry->type->isCredit()) {
                    $isDebit = true;
                } else {
                    $isCredit = true;
                }
            }

            $creditStr = $isCredit ? $amt : '0.0000';
            $debitStr = $isDebit ? $amt : '0.0000';

            // Before start date -> contributes to opening balance
            if ($startDate !== null && $postedAtDate < $startDate) {
                if ($isCredit) {
                    $openingBalance = Decimal::add($openingBalance, $amt);
                } elseif ($isDebit) {
                    $openingBalance = Decimal::sub($openingBalance, $amt);
                }
                $runningBalance = $openingBalance;

                continue;
            }

            // After end date -> ignored
            if ($endDate !== null && $postedAtDate > $endDate) {
                continue;
            }

            if ($isCredit) {
                $runningBalance = Decimal::add($runningBalance, $amt);
            } elseif ($isDebit) {
                $runningBalance = Decimal::sub($runningBalance, $amt);
            }

            $statementEntries[] = [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'type' => $entry->type,
                'amount' => $amt,
                'credit' => $creditStr,
                'debit' => $debitStr,
                'running_balance' => $runningBalance,
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'source_event' => $entry->source_event,
                'journal_entry_id' => $entry->journal_entry_id,
                'reversal_of_id' => $entry->reversal_of_id,
                'posted_at' => $entry->posted_at,
            ];
        }

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'entries' => $statementEntries,
        ];
    }
}
