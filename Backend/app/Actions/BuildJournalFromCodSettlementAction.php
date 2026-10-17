<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\TreasuryCurrencyAccount;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Support\TreasuryAccountResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final class BuildJournalFromCodSettlementAction
{
    public function __construct(
        private readonly GenerateJournalEntryNumberAction $numberGenerator,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(DriverCodSettlement $settlement, int $userId = 0): JournalEntry
    {
        return DB::transaction(function () use ($settlement, $userId): JournalEntry {
            // 1. Idempotency: if already created and posted, return existing
            /** @var JournalEntry|null $existing */
            $existing = JournalEntry::query()
                ->where('source_type', DriverCodSettlement::class)
                ->where('source_id', $settlement->id)
                ->where('source_event', 'POSTED')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // 2. Validate Settlement State
            if ((float) $settlement->total_settled <= 0) {
                throw new DomainException('مبلغ التسوية يجب أن يكون أكبر من الصفر لإنشاء القيد المحاسبي.');
            }

            // 3. Resolve Treasury Debit Account
            $treasury = $settlement->treasuryCurrencyAccount
                ?? TreasuryAccountResolver::resolve($settlement->treasuryAccount, 'حساب الخزينة غير محدد أو غير صالح.');

            $debitAccountId = null;
            if ($settlement->treasury_currency_account_id && $settlement->treasuryCurrencyAccount) {
                $debitAccountId = (int) $settlement->treasuryCurrencyAccount->gl_account_id;
            } else {
                $treasuryAccountModel = $treasury;
                if ($treasuryAccountModel instanceof TreasuryCurrencyAccount) {
                    $debitAccountId = (int) $treasuryAccountModel->gl_account_id;
                } else {
                    $currAcc = $treasuryAccountModel->currencyAccounts()->where('currency_id', $settlement->currency_id)->first();
                    $debitAccountId = $currAcc ? (int) $currAcc->gl_account_id : (int) $treasuryAccountModel->gl_account_id;
                }
            }

            if (! $debitAccountId) {
                throw new DomainException('حساب الخزينة غير محدد أو غير مرتبط بحساب في الأستاذ العام.');
            }

            // 4. Resolve Driver COD Clearing Credit Account
            $mapping = AccountingMapping::query()
                ->with('creditAccount')
                ->where('event_key', 'DRIVER_COD_SETTLEMENT_POSTED')
                ->where('is_active', true)
                ->first();

            $creditAccountId = $mapping?->credit_account_id;
            if (! $creditAccountId) {
                $fallbackAccount = Account::query()->where('code', '1160')->first();
                $creditAccountId = $fallbackAccount?->id;
            }

            if (! $creditAccountId) {
                throw new DomainException('لم يتم العثور على حساب وسيط تحصيل عهد المناديب (1160) أو إعداد توجيه محاسبي نشط.');
            }

            // 5. Check Open Accounting Period
            $now = now();
            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('status', AccountingPeriodStatus::Open)
                ->where('starts_at', '<=', $now->toDateString())
                ->where('ends_at', '>=', $now->toDateString())
                ->first();

            if ($period === null) {
                // Fallback to any open period
                $period = AccountingPeriod::query()
                    ->where('status', AccountingPeriodStatus::Open)
                    ->orderBy('starts_at')
                    ->first();
            }

            if ($period === null) {
                throw new DomainException('لا توجد فترة محاسبية مفتوحة لإجراء ترحيل قيد التسوية.');
            }

            // 6. Currency & Exchange Rate
            $baseCurrency = $this->currencyConverter->getBaseCurrency();
            $currency = $settlement->currency ?? Currency::query()->findOrFail($settlement->currency_id);
            $transactionAmount = (string) $settlement->total_settled;

            if ($currency->id === $baseCurrency->id) {
                $baseAmount = number_format((float) $transactionAmount, 4, '.', '');
                $exchangeRate = '1.00000000';
            } else {
                $rateInfo = $this->currencyConverter->resolveRate($currency, $baseCurrency, $now);
                if ($rateInfo === null) {
                    throw new DomainException(sprintf('لا يوجد سعر صرف معرف بين عملة التسوية (%s) والعملة الأساسية (%s).', $currency->code, $baseCurrency->code));
                }
                $exchangeRate = $rateInfo['rate'];
                $baseAmount = number_format((float) bcmul($transactionAmount, $exchangeRate, 4), 4, '.', '');
            }

            $effectiveUserId = $userId > 0 ? $userId : (auth()->id() ?: null);
            $entryNumber = $this->numberGenerator->execute($now);

            // 7. Create Journal Entry
            $driverName = $settlement->driver->name ?? 'مندوب';
            $treasuryName = $treasury->displayName;

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'accounting_period_id' => $period->id,
                'entry_date' => $now->toDateString(),
                'status' => JournalEntryStatus::Posted,
                'description' => sprintf('تسوية تحصيل نقدية COD رقم %s - المندوب: %s -> الحساب: %s', $settlement->settlement_number, $driverName, $treasuryName),
                'source_type' => DriverCodSettlement::class,
                'source_id' => $settlement->id,
                'source_event' => 'POSTED',
                'currency_id' => $currency->id,
                'exchange_rate_snapshot' => $exchangeRate,
                'total_debit' => $baseAmount,
                'total_credit' => $baseAmount,
                'posted_at' => $now,
                'posted_by' => $effectiveUserId,
                'created_by' => $effectiveUserId,
                'updated_by' => $effectiveUserId,
            ]);

            // 8. Line 1: Debit (Treasury Asset Account)
            $treasuryCurrencyAccountId = $settlement->treasury_currency_account_id
                ? (int) $settlement->treasury_currency_account_id
                : null;
            if ($treasuryCurrencyAccountId === null && $treasury instanceof TreasuryCurrencyAccount) {
                $treasuryCurrencyAccountId = (int) $treasury->id;
            } elseif ($treasuryCurrencyAccountId === null) {
                $matched = $treasury->currencyAccounts()
                    ->where('currency_id', $settlement->currency_id)
                    ->value('id');
                $treasuryCurrencyAccountId = $matched ? (int) $matched : null;
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $debitAccountId,
                'treasury_currency_account_id' => $treasuryCurrencyAccountId,
                'description' => sprintf('إيداع نقدي بالخزينة من تسوية عهدة المندوب %s (%s)', $driverName, $settlement->settlement_number),
                'debit' => $baseAmount,
                'credit' => '0.0000',
                'transaction_currency_id' => $currency->id,
                'transaction_amount' => $transactionAmount,
                'exchange_rate_snapshot' => $exchangeRate,
                'base_currency_amount' => $baseAmount,
                'party_type' => get_class($treasury),
                'party_id' => $treasury->id,
                'line_order' => 1,
            ]);

            // 9. Line 2: Credit (Driver COD Clearing Account)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $creditAccountId,
                'description' => sprintf('تخفيض عهدة المندوب %s بموجب تسوية COD رقم %s', $driverName, $settlement->settlement_number),
                'debit' => '0.0000',
                'credit' => $baseAmount,
                'transaction_currency_id' => $currency->id,
                'transaction_amount' => $transactionAmount,
                'exchange_rate_snapshot' => $exchangeRate,
                'base_currency_amount' => $baseAmount,
                'party_type' => Driver::class,
                'party_id' => $settlement->driver_id,
                'line_order' => 2,
            ]);

            // 10. Audit Log
            $this->auditLogger->record('admin.journal_entry.source_posted', $entry, metadata: [
                'source' => 'driver_cod_settlement',
                'settlement_number' => $settlement->settlement_number,
                'total' => $baseAmount,
            ]);

            return $entry->fresh(['lines.account', 'currency', 'accountingPeriod']);
        });
    }
}
