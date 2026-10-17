<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\Decimal;

final class LoyaltyCalculationService
{
    public function getSpendPerPoint(): string
    {
        $val = Setting::where('key', 'loyalty.spend_per_point')->value('value');

        return $val !== null && bccomp((string) $val, '0', 4) > 0
            ? Decimal::normalize($val)
            : '1000.0000';
    }

    public function getMinOrderAmount(): string
    {
        $val = Setting::where('key', 'loyalty.min_order_amount')->value('value');

        return $val !== null && bccomp((string) $val, '0', 4) > 0
            ? Decimal::normalize($val)
            : '0.0000';
    }

    public function getMaxPointsPerOrder(): int
    {
        $val = Setting::where('key', 'loyalty.max_points_per_order')->value('value');

        return $val !== null ? max(0, (int) $val) : 0;
    }

    public function calculateOrderPoints(Order $order): int
    {
        $baseTotal = Decimal::normalize($order->base_currency_total);

        $minAmount = $this->getMinOrderAmount();
        if (bccomp($baseTotal, $minAmount, 4) < 0) {
            return 0;
        }

        $spendPerPoint = $this->getSpendPerPoint();
        if (bccomp($spendPerPoint, '0', 4) <= 0) {
            return 0;
        }

        $div = Decimal::div($baseTotal, $spendPerPoint);
        $points = (int) floor((float) $div);

        $maxCap = $this->getMaxPointsPerOrder();
        if ($maxCap > 0 && $points > $maxCap) {
            $points = $maxCap;
        }

        return max(0, $points);
    }

    public function calculateReturnClawbackPoints(string $orderBaseTotal, string $returnBaseTotal, int $earnedPoints): int
    {
        if ($earnedPoints <= 0 || bccomp($orderBaseTotal, '0', 4) <= 0 || bccomp($returnBaseTotal, '0', 4) <= 0) {
            return 0;
        }

        $orderTotalDec = Decimal::normalize($orderBaseTotal);
        $returnTotalDec = Decimal::normalize($returnBaseTotal);

        if (bccomp($returnTotalDec, $orderTotalDec, 4) >= 0) {
            return $earnedPoints;
        }

        $ratio = Decimal::div($returnTotalDec, $orderTotalDec);
        $pointsToClawback = (int) round((float) $ratio * $earnedPoints);

        return min($earnedPoints, max(0, $pointsToClawback));
    }
}
