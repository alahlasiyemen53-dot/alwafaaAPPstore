<?php

namespace App\Services;

use App\Enums\LoyaltyEntryType;
use App\Models\CustomerLoyaltyAccount;

final class LoyaltyBalanceQuery
{
    /**
     * @return array{
     *     available_points: int,
     *     lifetime_earned: int,
     *     lifetime_redeemed: int,
     *     active_reserved_points: int,
     *     total_credit: int,
     *     total_debit: int
     * }
     */
    public function calculate(CustomerLoyaltyAccount $account): array
    {
        $creditTypes = [
            LoyaltyEntryType::Earn->value,
            LoyaltyEntryType::AdjustmentCredit->value,
        ];

        $debitTypes = [
            LoyaltyEntryType::Redeem->value,
            LoyaltyEntryType::Clawback->value,
            LoyaltyEntryType::AdjustmentDebit->value,
        ];

        $totalCredit = (int) $account->entries()
            ->whereIn('type', $creditTypes)
            ->sum('points');

        $totalDebit = (int) $account->entries()
            ->whereIn('type', $debitTypes)
            ->sum('points');

        $reservedPoints = (int) $account->reservations()
            ->where('status', 'active')
            ->sum('points');

        $lifetimeEarned = (int) $account->entries()
            ->where('type', LoyaltyEntryType::Earn->value)
            ->sum('points');

        $lifetimeRedeemed = (int) $account->entries()
            ->where('type', LoyaltyEntryType::Redeem->value)
            ->sum('points');

        $availablePoints = max(0, $totalCredit - $totalDebit - $reservedPoints);

        return [
            'available_points' => $availablePoints,
            'lifetime_earned' => $lifetimeEarned,
            'lifetime_redeemed' => $lifetimeRedeemed,
            'active_reserved_points' => $reservedPoints,
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
        ];
    }

    public function getAvailablePoints(CustomerLoyaltyAccount $account): int
    {
        return $this->calculate($account)['available_points'];
    }

    public function getBalance(CustomerLoyaltyAccount $account): int
    {
        return $this->getAvailablePoints($account);
    }
}
