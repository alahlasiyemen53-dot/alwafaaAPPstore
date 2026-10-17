<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\DriverCodSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GenerateDriverCodSettlementNumberAction
{
    public function execute(?Carbon $date = null): string
    {
        $targetDate = $date ?? now();
        $prefix = 'COD-SET-'.$targetDate->format('Ym');

        return DB::transaction(function () use ($prefix): string {
            /** @var DriverCodSettlement|null $latest */
            $latest = DriverCodSettlement::query()
                ->where('settlement_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('settlement_number')
                ->first();

            if ($latest === null) {
                return "{$prefix}-0001";
            }

            $parts = explode('-', (string) $latest->settlement_number);
            $sequence = isset($parts[3]) ? (int) $parts[3] : 0;
            $nextSequence = $sequence + 1;

            do {
                $candidate = sprintf('%s-%04d', $prefix, $nextSequence);
                $exists = DriverCodSettlement::query()->where('settlement_number', $candidate)->exists();
                if (! $exists) {
                    return $candidate;
                }
                $nextSequence++;
            } while (true);
        });
    }
}
