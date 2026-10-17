<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\LoyaltyEntryType;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Services\LoyaltyBalanceQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Loyalty activity report from the ledger. Earn/redeem/clawback/adjustment
 * entries are summed independently per type; reversal entries are shown
 * separately and never relabeled as original activity. Available points
 * remain ledger-derived via LoyaltyBalanceQuery.
 */
final class LoyaltyReportQuery
{
    private const ALLOWED_SORTS = ['id', 'created_at'];

    public function __construct(
        private readonly LoyaltyBalanceQuery $balanceQuery,
    ) {}

    /**
     * @return array{
     *     activity: array<string, int>,
     *     customers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = CustomerLoyaltyEntry::query()
            ->where('created_at', '>=', $filters->range->startAt)
            ->where('created_at', '<', $filters->range->endAt);

        if ($filters->customerId !== null) {
            $base->whereHas('account', fn ($q) => $q->where('customer_id', $filters->customerId));
        }

        $activity = [];
        foreach (LoyaltyEntryType::cases() as $type) {
            $activity[$type->value] = (int) (clone $base)->where('type', $type)->sum('points');
        }

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $accountsQuery = CustomerLoyaltyAccount::query()
            ->whereHas('entries', function ($q) use ($filters): void {
                $q->where('created_at', '>=', $filters->range->startAt)
                    ->where('created_at', '<', $filters->range->endAt);
            })
            ->with('customer:id,name');

        if ($filters->customerId !== null) {
            $accountsQuery->where('customer_id', $filters->customerId);
        }

        $accounts = $accountsQuery
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        $accounts->getCollection()->transform(function (CustomerLoyaltyAccount $account) {
            $account->dynamic_balance = $this->balanceQuery->getBalance($account);

            return $account;
        });

        return [
            'activity' => $activity,
            'customers' => $accounts,
        ];
    }
}
