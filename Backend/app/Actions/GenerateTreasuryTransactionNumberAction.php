<?php

namespace App\Actions;

use App\Models\TreasuryTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GenerateTreasuryTransactionNumberAction
{
    public function execute(?Carbon $date = null): string
    {
        $targetDate = $date ?? now();
        $prefix = 'TRX-'.$targetDate->format('Ym');

        return DB::transaction(function () use ($prefix): string {
            $latest = TreasuryTransaction::query()
                ->where('transaction_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('transaction_number')
                ->first();

            if ($latest === null) {
                return "{$prefix}-0001";
            }

            $parts = explode('-', (string) $latest->transaction_number);
            $sequence = isset($parts[2]) ? (int) $parts[2] : 0;
            $nextSequence = $sequence + 1;

            do {
                $candidate = sprintf('%s-%04d', $prefix, $nextSequence);
                $exists = TreasuryTransaction::query()->where('transaction_number', $candidate)->exists();
                if (! $exists) {
                    return $candidate;
                }
                $nextSequence++;
            } while (true);
        });
    }
}
