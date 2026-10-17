<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Enums\CustomerWalletEntryType;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Services\CustomerWalletBalanceQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer Wallet liability report. This is the company's liability
 * ledger to customers, separate from the Sprint 14 company Electronic
 * Wallet treasury asset. Balances remain ledger-derived and must
 * reconcile with the GL liability account.
 */
final class CustomerWalletReportQuery
{
    private const ALLOWED_SORTS = ['id', 'created_at'];

    public function __construct(
        private readonly CustomerWalletBalanceQuery $balanceQuery,
    ) {}

    /**
     * @return array{
     *     liability_by_currency: array<int, array{currency_id: int, currency_code: string, credits: string, debits: string, liability: string}>,
     *     activity: array<string, string>,
     *     customers: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = CustomerWalletEntry::query()
            ->where('posted_at', '>=', $filters->range->startAt)
            ->where('posted_at', '<', $filters->range->endAt);

        if ($filters->customerId !== null) {
            $base->whereHas('account', fn ($q) => $q->where('customer_id', $filters->customerId));
        }

        $liabilityRows = (clone $base)
            ->toBase()->join('currencies', 'currencies.id', '=', 'customer_wallet_entries.currency_id')
            ->groupBy('customer_wallet_entries.currency_id', 'currencies.code')
            ->selectRaw("customer_wallet_entries.currency_id, currencies.code as currency_code,
                SUM(CASE WHEN customer_wallet_entries.type IN ('refund_credit', 'manual_credit') THEN customer_wallet_entries.amount ELSE 0 END) as credits,
                SUM(CASE WHEN customer_wallet_entries.type = 'manual_debit' THEN customer_wallet_entries.amount ELSE 0 END) as debits")
            ->get();

        $liabilityByCurrency = $liabilityRows->map(fn ($row): array => [
            'currency_id' => (int) $row->currency_id,
            'currency_code' => (string) $row->currency_code,
            'credits' => (string) $row->credits,
            'debits' => (string) $row->debits,
            'liability' => bcsub((string) $row->credits, (string) $row->debits, 4),
        ])->all();

        $activity = [];
        foreach (CustomerWalletEntryType::cases() as $type) {
            $activity[$type->value] = (string) ((clone $base)->where('type', $type)->sum('amount'));
        }

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'created_at';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $accountsQuery = CustomerWalletAccount::query()
            ->whereHas('entries', function ($q) use ($filters): void {
                $q->where('posted_at', '>=', $filters->range->startAt)
                    ->where('posted_at', '<', $filters->range->endAt);
            })
            ->with(['customer:id,name', 'currency:id,code']);

        if ($filters->customerId !== null) {
            $accountsQuery->where('customer_id', $filters->customerId);
        }
        if ($filters->currencyId !== null) {
            $accountsQuery->where('currency_id', $filters->currencyId);
        }

        $accounts = $accountsQuery
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        $accounts->getCollection()->transform(function (CustomerWalletAccount $account) {
            $account->dynamic_balance = $this->balanceQuery->getBalance($account);

            return $account;
        });

        return [
            'liability_by_currency' => $liabilityByCurrency,
            'activity' => $activity,
            'customers' => $accounts,
        ];
    }
}
