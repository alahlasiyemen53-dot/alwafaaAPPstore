<?php

namespace App\Services;

use App\Contracts\TreasuryAccount;
use App\Enums\JournalEntryStatus;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TreasuryBalanceService
{
    /**
     * Calculate authoritative balance for an operational treasury account from posted journal entries.
     *
     * Prefer lines stamped with treasury_currency_account_id so multiple parents
     * sharing one GL stay separated. Fall back to gl + currency for legacy
     * unstamped lines when no stamped lines exist for that TCA.
     */
    public function getAuthoritativeBalance(Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount|TreasuryAccount $account, ?Carbon $asOf = null): string
    {
        if (! $account instanceof Model) {
            return '0.0000';
        }

        $glAccountId = (int) $account->getAttribute('gl_account_id');
        if (! $glAccountId) {
            return '0.0000';
        }

        $currencyId = (int) $account->getAttribute('currency_id');
        if (! $currencyId) {
            return '0.0000';
        }

        $tcaId = $this->resolveTreasuryCurrencyAccountId($account);

        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $glAccountId)
            ->where('journal_entry_lines.transaction_currency_id', $currencyId)
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

        $this->applyTreasuryCurrencyAccountFilter($query, $tcaId);

        if ($asOf !== null) {
            $query->where('journal_entries.entry_date', '<=', $asOf->toDateString());
        }

        $totals = $query->selectRaw('
            COALESCE(SUM(CASE WHEN journal_entry_lines.debit > 0 THEN journal_entry_lines.transaction_amount ELSE 0 END), 0) as total_incoming,
            COALESCE(SUM(CASE WHEN journal_entry_lines.credit > 0 THEN journal_entry_lines.transaction_amount ELSE 0 END), 0) as total_outgoing
        ')->first();

        $incoming = (string) ($totals->total_incoming ?? '0');
        $outgoing = (string) ($totals->total_outgoing ?? '0');

        return bcsub($incoming, $outgoing, 4);
    }

    /**
     * Balance in base currency (debit − credit on journal lines) for the same
     * operational filter used by getAuthoritativeBalance.
     */
    public function getAuthoritativeBaseBalance(Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount|TreasuryAccount $account, ?Carbon $asOf = null): string
    {
        if (! $account instanceof Model) {
            return '0.0000';
        }

        $glAccountId = (int) $account->getAttribute('gl_account_id');
        if (! $glAccountId) {
            return '0.0000';
        }

        $currencyId = (int) $account->getAttribute('currency_id');
        if (! $currencyId) {
            return '0.0000';
        }

        $tcaId = $this->resolveTreasuryCurrencyAccountId($account);

        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $glAccountId)
            ->where('journal_entry_lines.transaction_currency_id', $currencyId)
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

        $this->applyTreasuryCurrencyAccountFilter($query, $tcaId);

        if ($asOf !== null) {
            $query->where('journal_entries.entry_date', '<=', $asOf->toDateString());
        }

        $totals = $query->selectRaw('
            COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit,
            COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit
        ')->first();

        $debit = (string) ($totals->total_debit ?? '0');
        $credit = (string) ($totals->total_credit ?? '0');

        return bcsub($debit, $credit, 4);
    }

    /**
     * Lock the operational account row and ensure sufficient available funds under concurrency.
     */
    public function lockAndEnsureSufficientBalance(Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount|TreasuryAccount $account, float|string $outgoingAmount): void
    {
        // Concurrency protection: lock the operational account row
        $lockedAccount = match (true) {
            $account instanceof Cashbox => Cashbox::query()->where('id', $account->id)->lockForUpdate()->first(),
            $account instanceof BankAccount => BankAccount::query()->where('id', $account->id)->lockForUpdate()->first(),
            $account instanceof ElectronicWalletAccount => ElectronicWalletAccount::query()->where('id', $account->id)->lockForUpdate()->first(),
            $account instanceof TreasuryCurrencyAccount => TreasuryCurrencyAccount::query()->where('id', $account->id)->lockForUpdate()->first(),
            default => null,
        };

        if (! $lockedAccount instanceof TreasuryAccount) {
            throw new UnprocessableEntityHttpException('حساب الخزينة غير موجود.');
        }

        if (! $lockedAccount->is_active) {
            throw new UnprocessableEntityHttpException('حساب الخزينة معطل ولا يمكن إجراء عمليات عليه.');
        }

        $currentBalance = $this->getAuthoritativeBalance($lockedAccount);
        $outgoingStr = number_format((float) $outgoingAmount, 4, '.', '');

        if (bccomp($currentBalance, $outgoingStr, 4) < 0) {
            throw new UnprocessableEntityHttpException('الرصيد المتاح غير كافٍ لتنفيذ هذه الحركة.');
        }
    }

    private function resolveTreasuryCurrencyAccountId(Model $account): ?int
    {
        if ($account instanceof TreasuryCurrencyAccount) {
            return (int) $account->id;
        }

        if (
            ! $account instanceof Cashbox
            && ! $account instanceof BankAccount
            && ! $account instanceof ElectronicWalletAccount
        ) {
            return null;
        }

        $currencyId = (int) $account->getAttribute('currency_id');
        $tcaId = TreasuryCurrencyAccount::query()
            ->where('accountable_type', get_class($account))
            ->where('accountable_id', $account->getKey())
            ->where('currency_id', $currencyId)
            ->value('id');

        return $tcaId ? (int) $tcaId : null;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyTreasuryCurrencyAccountFilter($query, ?int $tcaId): void
    {
        if ($tcaId === null) {
            return;
        }

        $hasStamped = DB::table('journal_entry_lines')
            ->where('treasury_currency_account_id', $tcaId)
            ->exists();

        if ($hasStamped) {
            $query->where('journal_entry_lines.treasury_currency_account_id', $tcaId);

            return;
        }

        // Legacy unstamped lines only (pre shared-GL stamp).
        $query->whereNull('journal_entry_lines.treasury_currency_account_id');
    }
}
