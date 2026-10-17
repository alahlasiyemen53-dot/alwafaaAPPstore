<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Decimal;

final class DiscountAllocationService
{
    /**
     * Proportionally allocate a total discount amount across multiple line items based on their base line totals.
     * Guarantees that the sum of allocated discounts strictly equals the total discount (down to 4 decimal places).
     *
     * @param  array<int|string, float|string>  $lineAmounts  Associative array or list of [id => amount]
     * @param  float|string  $totalDiscount  Total discount to allocate
     * @return array<int|string, float> [id => allocated_discount]
     */
    public function allocate(array $lineAmounts, float|string $totalDiscount): array
    {
        $totalDiscountStr = Decimal::max('0.0000', Decimal::normalize($totalDiscount));
        if (Decimal::compare($totalDiscountStr, '0.0000') <= 0 || empty($lineAmounts)) {
            $zeros = [];
            foreach ($lineAmounts as $key => $amount) {
                $zeros[$key] = 0.0;
            }

            return $zeros;
        }

        $sumAmountsStr = '0.0000';
        foreach ($lineAmounts as $amount) {
            $sumAmountsStr = Decimal::add($sumAmountsStr, Decimal::normalize($amount));
        }

        if (Decimal::compare($sumAmountsStr, '0.0000') <= 0) {
            $zeros = [];
            foreach ($lineAmounts as $key => $amount) {
                $zeros[$key] = 0.0;
            }

            return $zeros;
        }

        // Cap discount at total eligible amount
        $effectiveDiscountStr = Decimal::min($totalDiscountStr, $sumAmountsStr);

        $allocations = [];
        $runningSumStr = '0.0000';
        $keys = array_keys($lineAmounts);
        $lastIndex = count($keys) - 1;

        foreach ($keys as $idx => $key) {
            $lineAmountStr = Decimal::normalize($lineAmounts[$key]);

            if ($idx === $lastIndex) {
                // Assign residual to the last item to prevent rounding drift
                $allocatedStr = Decimal::sub($effectiveDiscountStr, $runningSumStr);
            } else {
                // Proportional allocation: (effectiveDiscount * lineAmount) / sumAmounts with higher scale then rounded
                $rawAllocated = bcdiv(
                    bcmul($effectiveDiscountStr, $lineAmountStr, 8),
                    $sumAmountsStr,
                    6
                );
                $allocatedStr = Decimal::normalize((float) $rawAllocated);
                $runningSumStr = Decimal::add($runningSumStr, $allocatedStr);
            }

            // Ensure allocated discount does not exceed the line amount
            $allocatedStr = Decimal::min($allocatedStr, $lineAmountStr);
            $allocatedStr = Decimal::max('0.0000', $allocatedStr);
            $allocations[$key] = (float) $allocatedStr;
        }

        return $allocations;
    }
}
