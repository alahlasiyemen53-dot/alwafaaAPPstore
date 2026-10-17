<?php

namespace App\Actions;

use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GenerateJournalEntryNumberAction
{
    public function execute(?Carbon $date = null): string
    {
        $targetDate = $date ?? now();
        $prefix = 'JE-'.$targetDate->format('Ym');

        return DB::transaction(function () use ($prefix): string {
            // Lock latest matching entry in this month
            $latest = JournalEntry::query()
                ->where('entry_number', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->orderByDesc('entry_number')
                ->first();

            if ($latest === null) {
                return "{$prefix}-0001";
            }

            $parts = explode('-', (string) $latest->entry_number);
            $sequence = isset($parts[2]) ? (int) $parts[2] : 0;
            $nextSequence = $sequence + 1;

            // Ensure uniqueness in case of gaps
            do {
                $candidate = sprintf('%s-%04d', $prefix, $nextSequence);
                $exists = JournalEntry::query()->where('entry_number', $candidate)->exists();
                if (! $exists) {
                    return $candidate;
                }
                $nextSequence++;
            } while (true);
        });
    }
}
