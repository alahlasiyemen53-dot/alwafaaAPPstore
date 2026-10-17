<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\CustomerSearchHistory;
use App\Support\SearchQueryNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordSearchHistoryAction
{
    public function execute(Customer $customer, string $query): ?CustomerSearchHistory
    {
        $display = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $normalized = SearchQueryNormalizer::normalize($display);

        if ($normalized === '' || mb_strlen($normalized) < 2) {
            return null;
        }

        return DB::transaction(function () use ($customer, $display, $normalized) {
            $record = CustomerSearchHistory::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'normalized_query' => $normalized,
                ],
                [
                    'query' => mb_substr($display, 0, 191),
                    'last_searched_at' => Carbon::now(),
                ],
            );

            $this->prune($customer);

            return $record;
        });
    }

    private function prune(Customer $customer): void
    {
        $limit = max(1, (int) config('customer_experience.search_history_limit', 15));

        $keepIds = CustomerSearchHistory::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->pluck('id');

        CustomerSearchHistory::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
