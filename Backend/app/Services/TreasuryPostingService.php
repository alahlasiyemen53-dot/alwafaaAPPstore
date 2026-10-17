<?php

namespace App\Services;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\Actions\ReverseJournalEntryAction;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\DTOs\ReverseJournalEntryData;
use App\Enums\AccountType;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Models\TreasuryTransaction;
use App\Support\TreasuryAccountResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TreasuryPostingService
{
    public function __construct(
        private readonly TreasuryBalanceService $balanceService,
        private readonly CurrencyConverter $currencyConverter,
        private readonly CreateDraftJournalEntryAction $createJournalAction,
        private readonly PostJournalEntryAction $postJournalAction,
        private readonly ReverseJournalEntryAction $reverseJournalAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function post(TreasuryTransaction $transaction): TreasuryTransaction
    {
        return DB::transaction(function () use ($transaction): TreasuryTransaction {
            /** @var TreasuryTransaction $lockedTrx */
            $lockedTrx = TreasuryTransaction::query()
                ->lockForUpdate()
                ->with(['currency', 'offsetAccount', 'journalEntry'])
                ->findOrFail($transaction->id);

            if ($lockedTrx->isPosted()) {
                return $lockedTrx;
            }

            if ($lockedTrx->isReversed()) {
                throw new ConflictHttpException('لا يمكن ترحيل حركة خزينة معكوسة.');
            }

            /** @var Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount $sourceAccount */
            $sourceAccount = $lockedTrx->currencyAccount
                ?? TreasuryAccountResolver::resolve($lockedTrx->account, 'حساب الخزينة المصدر غير موجود.');

            if (! $sourceAccount->is_active) {
                throw new UnprocessableEntityHttpException('حساب الخزينة المصدر معطل ولا يقبل ترحيل حركات.');
            }

            if ((int) $lockedTrx->currency_id !== (int) $sourceAccount->currency_id) {
                throw new UnprocessableEntityHttpException('عملة الحركة المالية لا تطابق عملة حساب الخزينة المحدد.');
            }

            $sourceGl = $sourceAccount->glAccount;
            if (! $sourceGl || ! $sourceGl->is_active || ! $sourceGl->is_postable) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام المربوط بحساب الخزينة المصدر غير صالح أو معطل أو غير قابل للترحيل.');
            }

            if ($sourceGl->type !== AccountType::Asset) {
                throw new UnprocessableEntityHttpException('يجب أن يكون حساب الأستاذ العام لحساب الخزينة من نوع الأصول (Asset).');
            }

            $baseCurrency = $this->currencyConverter->getBaseCurrency();
            $trxCurrency = $lockedTrx->currency ?? $sourceAccount->currency;

            // Resolve open accounting period
            $openPeriod = AccountingPeriod::open()
                ->whereDate('starts_at', '<=', now()->toDateString())
                ->whereDate('ends_at', '>=', now()->toDateString())
                ->first()
                ?? AccountingPeriod::open()->orderByDesc('starts_at')->first();

            if (! $openPeriod) {
                throw new UnprocessableEntityHttpException('لا توجد فترة محاسبية مفتوحة حالياً لترحيل الحركة.');
            }

            $lines = [];
            $amount = number_format((float) $lockedTrx->amount, 4, '.', '');
            $sourceTcaId = $this->resolveTreasuryCurrencyAccountId($sourceAccount, $lockedTrx->treasury_currency_account_id);

            if ($lockedTrx->transaction_type === TreasuryTransactionType::Transfer) {
                /** @var Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount $destAccount */
                $destAccount = $lockedTrx->destinationCurrencyAccount
                    ?? TreasuryAccountResolver::resolve($lockedTrx->destinationAccount, 'حساب الخزينة الوجهة غير محدد.');

                if (! $destAccount->is_active) {
                    throw new UnprocessableEntityHttpException('حساب الخزينة الوجهة معطل.');
                }

                if (
                    $lockedTrx->treasury_currency_account_id &&
                    $lockedTrx->destination_currency_account_id &&
                    $lockedTrx->treasury_currency_account_id === $lockedTrx->destination_currency_account_id
                ) {
                    throw new UnprocessableEntityHttpException('لا يمكن التحويل المالي إلى نفس الحساب.');
                }

                if ($lockedTrx->account_type === $lockedTrx->destination_type && $lockedTrx->account_id === $lockedTrx->destination_id) {
                    throw new UnprocessableEntityHttpException('لا يمكن التحويل المالي إلى نفس الحساب.');
                }

                $destGl = $destAccount->glAccount;
                if (! $destGl || ! $destGl->is_active || ! $destGl->is_postable) {
                    throw new UnprocessableEntityHttpException('حساب الأستاذ العام لحساب الخزينة الوجهة غير صالح أو معطل.');
                }

                if ($destGl->type !== AccountType::Asset) {
                    throw new UnprocessableEntityHttpException('يجب أن يكون حساب الأستاذ العام للوجهة من نوع الأصول.');
                }

                // Check same-currency constraint (Sprint 21.3 § 18, 19, 57)
                if ((int) $sourceAccount->currency_id !== (int) $destAccount->currency_id) {
                    throw new UnprocessableEntityHttpException('التحويل بين عملات مختلفة غير مدعوم في التحويلات العادية للخزينة. يجب أن تتطابق عملة المصدر والوجهة.');
                }

                // Check sufficient balance on source account
                $this->balanceService->lockAndEnsureSufficientBalance($sourceAccount, $amount);

                $destTcaId = $this->resolveTreasuryCurrencyAccountId($destAccount, $lockedTrx->destination_currency_account_id);

                // Build Transfer lines
                // Credit Source, Debit Destination
                $lines[] = new JournalEntryLineData(
                    accountId: $destGl->id,
                    description: "تحويل وارد من {$sourceAccount->displayName}: {$lockedTrx->description}",
                    debit: (float) $amount,
                    credit: 0.0,
                    transactionCurrencyId: $sourceAccount->currency_id,
                    transactionAmount: (float) $amount,
                    exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                    baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                    partyType: $lockedTrx->counterparty_type,
                    partyId: $lockedTrx->counterparty_id,
                    lineOrder: 1,
                    treasuryCurrencyAccountId: $destTcaId,
                );

                $lines[] = new JournalEntryLineData(
                    accountId: $sourceGl->id,
                    description: "تحويل صادر إلى {$destAccount->displayName}: {$lockedTrx->description}",
                    debit: 0.0,
                    credit: (float) $amount,
                    transactionCurrencyId: $sourceAccount->currency_id,
                    transactionAmount: (float) $amount,
                    exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                    baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                    partyType: $lockedTrx->counterparty_type,
                    partyId: $lockedTrx->counterparty_id,
                    lineOrder: 2,
                    treasuryCurrencyAccountId: $sourceTcaId,
                );
            } else {
                $offsetGl = $lockedTrx->offsetAccount;
                if (! $offsetGl || ! $offsetGl->is_active || ! $offsetGl->is_postable) {
                    throw new UnprocessableEntityHttpException('الحساب المحاسبي المقابل غير محدد أو غير قابل للترحيل.');
                }

                if ($lockedTrx->transaction_type->isOutgoing()) {
                    // Outgoing: Credit source account, Debit offset account
                    $this->balanceService->lockAndEnsureSufficientBalance($sourceAccount, $amount);

                    $lines[] = new JournalEntryLineData(
                        accountId: $offsetGl->id,
                        description: $lockedTrx->description,
                        debit: (float) $amount,
                        credit: 0.0,
                        transactionCurrencyId: $trxCurrency->id,
                        transactionAmount: (float) $amount,
                        exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                        baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                        partyType: $lockedTrx->counterparty_type,
                        partyId: $lockedTrx->counterparty_id,
                        lineOrder: 1,
                    );

                    $lines[] = new JournalEntryLineData(
                        accountId: $sourceGl->id,
                        description: $lockedTrx->description,
                        debit: 0.0,
                        credit: (float) $amount,
                        transactionCurrencyId: $trxCurrency->id,
                        transactionAmount: (float) $amount,
                        exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                        baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                        partyType: $lockedTrx->counterparty_type,
                        partyId: $lockedTrx->counterparty_id,
                        lineOrder: 2,
                        treasuryCurrencyAccountId: $sourceTcaId,
                    );
                } else {
                    // Incoming (Receipt, Deposit, Opening Balance, Adjustment): Debit source account, Credit offset account
                    $lines[] = new JournalEntryLineData(
                        accountId: $sourceGl->id,
                        description: $lockedTrx->description,
                        debit: (float) $amount,
                        credit: 0.0,
                        transactionCurrencyId: $trxCurrency->id,
                        transactionAmount: (float) $amount,
                        exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                        baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                        partyType: $lockedTrx->counterparty_type,
                        partyId: $lockedTrx->counterparty_id,
                        lineOrder: 1,
                        treasuryCurrencyAccountId: $sourceTcaId,
                    );

                    $lines[] = new JournalEntryLineData(
                        accountId: $offsetGl->id,
                        description: $lockedTrx->description,
                        debit: 0.0,
                        credit: (float) $amount,
                        transactionCurrencyId: $trxCurrency->id,
                        transactionAmount: (float) $amount,
                        exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                        baseCurrencyAmount: (float) $lockedTrx->base_currency_amount,
                        partyType: $lockedTrx->counterparty_type,
                        partyId: $lockedTrx->counterparty_id,
                        lineOrder: 2,
                    );
                }
            }

            // Create and Post Journal Entry
            $journalData = new CreateJournalEntryData(
                accountingPeriodId: $openPeriod->id,
                entryDate: now()->toDateString(),
                description: "قيد حركة الخزينة {$lockedTrx->transaction_number}: {$lockedTrx->description}",
                currencyId: $trxCurrency->id,
                exchangeRateSnapshot: $lockedTrx->exchange_rate_snapshot,
                sourceType: TreasuryTransaction::class,
                sourceId: $lockedTrx->id,
                sourceEvent: 'TREASURY_TRANSACTION_POSTED',
                lines: $lines,
            );

            $draftJournal = $this->createJournalAction->execute($journalData);
            $postedJournal = $this->postJournalAction->execute($draftJournal);

            $userId = $this->resolveActingUserId();

            $lockedTrx->update([
                'journal_entry_id' => $postedJournal->id,
                'status' => TreasuryTransactionStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $userId,
                'base_currency_amount' => $postedJournal->total_debit,
            ]);

            $this->auditLogger->record('admin.treasury_transaction.posted', $lockedTrx, metadata: [
                'journal_entry_id' => $postedJournal->id,
                'journal_entry_number' => $postedJournal->entry_number,
                'amount' => $lockedTrx->amount,
                'type' => $lockedTrx->transaction_type->value,
            ]);

            return $lockedTrx;
        });
    }

    public function reverse(TreasuryTransaction $transaction, string $reason): TreasuryTransaction
    {
        return DB::transaction(function () use ($transaction, $reason): TreasuryTransaction {
            /** @var TreasuryTransaction $lockedTrx */
            $lockedTrx = TreasuryTransaction::query()
                ->lockForUpdate()
                ->with(['journalEntry'])
                ->findOrFail($transaction->id);

            if (! $lockedTrx->isPosted()) {
                throw new ConflictHttpException('يمكن فقط عكس حركات الخزينة المرحلة.');
            }

            if ($lockedTrx->isReversed()) {
                throw new ConflictHttpException('هذه الحركة معكوسة مسبقاً.');
            }

            $journal = $lockedTrx->journalEntry;
            if (! $journal) {
                throw new UnprocessableEntityHttpException('لا يوجد قيد محاسبي مرتبط بهذه الحركة لعكسه.');
            }

            $openPeriod = AccountingPeriod::open()
                ->whereDate('starts_at', '<=', now()->toDateString())
                ->whereDate('ends_at', '>=', now()->toDateString())
                ->first()
                ?? AccountingPeriod::open()->orderByDesc('starts_at')->first();

            if (! $openPeriod) {
                throw new UnprocessableEntityHttpException('لا توجد فترة محاسبية مفتوحة حالياً لعكس القيد.');
            }

            $reversalData = new ReverseJournalEntryData(
                reason: $reason,
                reversalDate: now()->toDateString(),
                accountingPeriodId: $openPeriod->id,
            );

            $this->reverseJournalAction->execute($journal, $reversalData);

            $userId = $this->resolveActingUserId();

            $lockedTrx->update([
                'status' => TreasuryTransactionStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            $this->auditLogger->record('admin.treasury_transaction.reversed', $lockedTrx, metadata: [
                'reason' => $reason,
                'journal_entry_id' => $journal->id,
            ]);

            return $lockedTrx;
        });
    }

    private function resolveTreasuryCurrencyAccountId(
        Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount $account,
        ?int $preferredId = null,
    ): ?int {
        if ($preferredId !== null && $preferredId > 0) {
            return $preferredId;
        }

        if ($account instanceof TreasuryCurrencyAccount) {
            return (int) $account->id;
        }

        $currencyId = (int) $account->getAttribute('currency_id');
        $tcaId = TreasuryCurrencyAccount::query()
            ->where('accountable_type', get_class($account))
            ->where('accountable_id', $account->getKey())
            ->where('currency_id', $currencyId)
            ->value('id');

        return $tcaId ? (int) $tcaId : null;
    }

    private function resolveActingUserId(): ?int
    {
        $user = auth()->user();
        if ($user instanceof \App\Models\User) {
            return (int) $user->id;
        }

        return null;
    }
}
