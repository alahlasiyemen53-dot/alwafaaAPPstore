<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportDateRangeData;
use App\Enums\CouponUsageStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Queries\TrialBalanceQuery;
use App\Services\DriverCodReconciliationService;
use Illuminate\Support\Facades\DB;

/**
 * Composes existing authoritative reconciliation sources into deterministic
 * dashboard/report integrity indicators. Reports mismatches; it never
 * repairs them.
 */
final class FinancialIntegrityQuery
{
    public function __construct(
        private readonly TrialBalanceQuery $trialBalanceQuery,
        private readonly DriverCodReconciliationService $driverCodReconciliation,
    ) {}

    /**
     * @return array{
     *     trial_balance: array{is_balanced: bool, difference: string, has_open_period: bool},
     *     treasury: array{mismatched_currency_lines: int, status: 'OK'|'ISSUES'},
     *     cod: array{status: 'MATCHED'|'DISCREPANCY_FOUND'},
     *     inventory: array{mismatched_positions: int, status: 'OK'|'ISSUES'},
     *     wallet: array{wallet_ledger_total: string, gl_liability_total: string, status: 'OK'|'ISSUES'},
     *     coupons: array{active_usages: int, eligible_confirmed_orders: int, status: 'OK'|'ISSUES'}
     * }
     */
    public function execute(ReportDateRangeData $range): array
    {
        $openPeriod = AccountingPeriod::open()->exists();

        $trialBalance = $this->trialBalanceQuery->execute(
            null,
            $range->startAt->toDateString(),
            $range->endAt->subDay()->toDateString(),
        );

        $treasuryAccounts = DB::table('cashboxes')->select('gl_account_id', 'currency_id')
            ->unionAll(DB::table('bank_accounts')->select('gl_account_id', 'currency_id'))
            ->unionAll(DB::table('electronic_wallet_accounts')->select('gl_account_id', 'currency_id'));

        $treasuryMismatch = (int) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->joinSub($treasuryAccounts, 'treasury_accounts', 'treasury_accounts.gl_account_id', '=', 'journal_entry_lines.account_id')
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->whereColumn('journal_entry_lines.transaction_currency_id', '!=', 'treasury_accounts.currency_id')
            ->count();

        $codReconciliation = $this->driverCodReconciliation->reconcile();

        $inventoryMismatch = (int) DB::table('inventories')
            ->leftJoinSub(
                DB::table('stock_movements')
                    ->select('warehouse_id', 'product_variant_id')
                    ->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as net_quantity")
                    ->groupBy('warehouse_id', 'product_variant_id'),
                'movements',
                function ($join): void {
                    $join->on('movements.warehouse_id', '=', 'inventories.warehouse_id')
                        ->on('movements.product_variant_id', '=', 'inventories.product_variant_id');
                }
            )
            ->whereRaw('ABS(inventories.quantity - COALESCE(movements.net_quantity, 0)) > 0.0001')
            ->count();

        $walletGlAccount = Account::where('code', '2120')->first();
        $walletGlTotal = '0.0000';
        if ($walletGlAccount) {
            $glTotals = DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entry_lines.account_id', $walletGlAccount->id)
                ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
                ->selectRaw('COALESCE(SUM(credit), 0) as total_credit, COALESCE(SUM(debit), 0) as total_debit')
                ->first();
            $walletGlTotal = bcsub((string) $glTotals->total_credit, (string) $glTotals->total_debit, 4);
        }

        $walletLedgerTotal = (string) DB::table('customer_wallet_entries')
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('refund_credit', 'manual_credit') THEN amount WHEN type = 'reversal' THEN 0 ELSE -amount END), 0) as net")
            ->value('net');

        $activeCouponUsages = (int) DB::table('coupon_usages')->where('status', CouponUsageStatus::Active->value)->count();
        $eligibleConfirmedOrders = (int) DB::table('orders')
            ->whereNotNull('coupon_id')
            ->whereNotNull('confirmed_at')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->count();

        return [
            'trial_balance' => [
                'is_balanced' => (bool) $trialBalance['is_balanced'],
                'difference' => (string) $trialBalance['difference'],
                'has_open_period' => $openPeriod,
            ],
            'treasury' => [
                'mismatched_currency_lines' => $treasuryMismatch,
                'status' => $treasuryMismatch === 0 ? 'OK' : 'ISSUES',
            ],
            'cod' => [
                'status' => $codReconciliation['status'],
            ],
            'inventory' => [
                'mismatched_positions' => $inventoryMismatch,
                'status' => $inventoryMismatch === 0 ? 'OK' : 'ISSUES',
            ],
            'wallet' => [
                'wallet_ledger_total' => $walletLedgerTotal,
                'gl_liability_total' => $walletGlTotal,
                'status' => bccomp($walletLedgerTotal, $walletGlTotal, 4) === 0 ? 'OK' : 'ISSUES',
            ],
            'coupons' => [
                'active_usages' => $activeCouponUsages,
                'eligible_confirmed_orders' => $eligibleConfirmedOrders,
                'status' => $activeCouponUsages <= $eligibleConfirmedOrders ? 'OK' : 'ISSUES',
            ],
        ];
    }
}
