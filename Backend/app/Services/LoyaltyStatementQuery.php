<?php

namespace App\Services;

use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;

final class LoyaltyStatementQuery
{
    /**
     * @return array{
     *     account: CustomerLoyaltyAccount,
     *     opening_points: int,
     *     closing_points: int,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function getStatement(CustomerLoyaltyAccount $account, ?string $startDate = null, ?string $endDate = null): array
    {
        $allEntries = CustomerLoyaltyEntry::query()
            ->where('customer_loyalty_account_id', $account->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $openingPoints = 0;
        $runningPoints = 0;
        $statementEntries = [];

        foreach ($allEntries as $entry) {
            $occurredAtDate = $entry->occurred_at->toDateString();
            $pts = (int) $entry->points;

            $isEarned = $entry->type->isCredit();
            $isDeducted = $entry->type->isDebit();

            $earnedVal = $isEarned ? $pts : 0;
            $deductedVal = $isDeducted ? $pts : 0;

            if ($startDate !== null && $occurredAtDate < $startDate) {
                if ($isEarned) {
                    $openingPoints += $pts;
                } elseif ($isDeducted) {
                    $openingPoints -= $pts;
                }
                $runningPoints = $openingPoints;

                continue;
            }

            if ($endDate !== null && $occurredAtDate > $endDate) {
                continue;
            }

            if ($isEarned) {
                $runningPoints += $pts;
            } elseif ($isDeducted) {
                $runningPoints -= $pts;
            }

            $statementEntries[] = [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'type' => $entry->type,
                'points' => $pts,
                'earned_points' => $earnedVal,
                'deducted_points' => $deductedVal,
                'running_points' => $runningPoints,
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'source_event' => $entry->source_event,
                'occurred_at' => $entry->occurred_at,
            ];
        }

        return [
            'account' => $account,
            'opening_points' => $openingPoints,
            'closing_points' => $runningPoints,
            'entries' => $statementEntries,
        ];
    }
}
