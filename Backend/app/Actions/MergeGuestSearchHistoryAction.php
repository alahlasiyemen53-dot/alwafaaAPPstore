<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerSearchHistory;
use App\Support\SearchQueryNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MergeGuestSearchHistoryAction
{
    /**
     * @param  list<string>  $queries  Most-recent-first guest search queries.
     */
    public function execute(Customer $customer, array $queries): void
    {
        $limit = max(1, (int) config('customer_experience.search_history_limit', 15));

        /** @var list<array{normalized: string, display: string}> $ordered */
        $ordered = [];
        $seen = [];

        foreach ($queries as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $display = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
            $normalized = SearchQueryNormalizer::normalize($display);

            if ($normalized === '' || mb_strlen($normalized) < 2) {
                continue;
            }

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $ordered[] = [
                'normalized' => $normalized,
                'display' => mb_substr($display, 0, 191),
            ];

            if (count($ordered) >= $limit) {
                break;
            }
        }

        if ($ordered === []) {
            return;
        }

        DB::transaction(function () use ($customer, $ordered, $limit): void {
            $base = Carbon::now();

            // Oldest first → smaller timestamps; guest most-recent keeps the highest.
            foreach (array_reverse($ordered) as $offset => $entry) {
                CustomerSearchHistory::query()->updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'normalized_query' => $entry['normalized'],
                    ],
                    [
                        'query' => $entry['display'],
                        'last_searched_at' => $base->copy()->addSeconds($offset),
                    ],
                );
            }

            $keepIds = CustomerSearchHistory::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('last_searched_at')
                ->limit($limit)
                ->pluck('id');

            CustomerSearchHistory::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }
}
