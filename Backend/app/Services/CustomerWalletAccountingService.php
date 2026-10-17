<?php

namespace App\Services;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalEntryLineData;
use App\Models\AccountingMapping;
use App\Models\AccountingPeriod;
use App\Models\CustomerWalletAccount;
use App\Models\JournalEntry;
use App\Support\Decimal;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CustomerWalletAccountingService
{
    public function __construct(
        private readonly CreateDraftJournalEntryAction $createJournalAction,
        private readonly PostJournalEntryAction $postJournalAction,
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    public function postManualCredit(CustomerWalletAccount $account, string $amount, string $reason, int $userId): JournalEntry
    {
        return $this->postWalletJournal(
            account: $account,
            amount: $amount,
            eventKey: 'CUSTOMER_WALLET_MANUAL_CREDIT',
            sourceEvent: 'ADMIN_MANUAL_CREDIT',
            description: sprintf('شحن تسوية يدوية دائنة لمحفظة العميل رقم %d: %s', $account->customer_id, $reason),
            userId: $userId,
            isCredit: true
        );
    }

    public function postManualDebit(CustomerWalletAccount $account, string $amount, string $reason, int $userId): JournalEntry
    {
        return $this->postWalletJournal(
            account: $account,
            amount: $amount,
            eventKey: 'CUSTOMER_WALLET_MANUAL_DEBIT',
            sourceEvent: 'ADMIN_MANUAL_DEBIT',
            description: sprintf('خصم تسوية يدوية مدينة من محفظة العميل رقم %d: %s', $account->customer_id, $reason),
            userId: $userId,
            isCredit: false
        );
    }

    public function postRefundCredit(CustomerWalletAccount $account, string $amount, string $reason, int $userId, int $salesReturnId): JournalEntry
    {
        return $this->postWalletJournal(
            account: $account,
            amount: $amount,
            eventKey: 'CUSTOMER_WALLET_REFUND_CREDIT',
            sourceEvent: 'SALES_RETURN_REFUND',
            description: sprintf('استرداد قيمة مرتجع مبيعات رقم %d إلى محفظة العميل: %s', $salesReturnId, $reason),
            userId: $userId,
            isCredit: true,
            sourceType: 'SalesReturn',
            sourceId: $salesReturnId
        );
    }

    private function postWalletJournal(
        CustomerWalletAccount $account,
        string $amount,
        string $eventKey,
        string $sourceEvent,
        string $description,
        int $userId,
        bool $isCredit,
        ?string $sourceType = 'CustomerWalletAccount',
        ?int $sourceId = null,
    ): JournalEntry {
        $sourceId ??= $account->id;

        // 1. Resolve open accounting period
        $openPeriod = AccountingPeriod::open()
            ->whereDate('starts_at', '<=', now()->toDateString())
            ->whereDate('ends_at', '>=', now()->toDateString())
            ->first()
            ?? AccountingPeriod::open()->orderByDesc('starts_at')->first();

        if (! $openPeriod) {
            throw new UnprocessableEntityHttpException('لا توجد فترة محاسبية مفتوحة حالياً لترحيل حركة المحفظة.');
        }

        // 2. Resolve AccountingMapping
        $mapping = AccountingMapping::where('event_key', $eventKey)->where('is_active', true)->first();
        if (! $mapping) {
            throw new UnprocessableEntityHttpException("تعذر العثور على التوجيه المحاسبي المعرف ({$eventKey}).");
        }

        $debitAccount = $mapping->debitAccount;
        $creditAccount = $mapping->creditAccount;

        if (! $debitAccount || ! $debitAccount->is_active || ! $debitAccount->is_postable) {
            throw new UnprocessableEntityHttpException('حساب المدين المحدد في التوجيه المحاسبي معطل أو غير قابل للترحيل.');
        }

        if (! $creditAccount || ! $creditAccount->is_active || ! $creditAccount->is_postable) {
            throw new UnprocessableEntityHttpException('حساب الدائن المحدد في التوجيه المحاسبي معطل أو غير قابل للترحيل.');
        }

        // 3. Resolve Currency & Rate
        $currency = $account->currency;
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        if ($currency->id === $baseCurrency->id || $currency->is_default) {
            $rate = '1.00000000';
            $baseAmount = Decimal::normalize($amount);
        } else {
            $rateInfo = $this->currencyConverter->resolveRate($currency, $baseCurrency);
            $rate = $rateInfo ? (string) $rateInfo['rate'] : '1.00000000';
            $baseAmount = Decimal::mul(Decimal::normalize($amount), $rate);
        }

        $normalizedAmount = Decimal::normalize($amount);

        // 4. Build Double Entry lines
        $lines = [
            new JournalEntryLineData(
                accountId: $debitAccount->id,
                description: $description,
                debit: $normalizedAmount,
                credit: '0.0000',
                transactionCurrencyId: $currency->id,
                transactionAmount: $normalizedAmount,
                exchangeRateSnapshot: $rate,
                baseCurrencyAmount: $baseAmount,
                partyType: 'Customer',
                partyId: $account->customer_id,
                lineOrder: 1,
            ),
            new JournalEntryLineData(
                accountId: $creditAccount->id,
                description: $description,
                debit: '0.0000',
                credit: $normalizedAmount,
                transactionCurrencyId: $currency->id,
                transactionAmount: $normalizedAmount,
                exchangeRateSnapshot: $rate,
                baseCurrencyAmount: $baseAmount,
                partyType: 'Customer',
                partyId: $account->customer_id,
                lineOrder: 2,
            ),
        ];

        $journalData = new CreateJournalEntryData(
            accountingPeriodId: $openPeriod->id,
            entryDate: now()->toDateString(),
            description: $description,
            currencyId: $currency->id,
            exchangeRateSnapshot: $rate,
            sourceType: $sourceType,
            sourceId: $sourceId,
            sourceEvent: $sourceEvent,
            lines: $lines,
        );

        $draftJournal = $this->createJournalAction->execute($journalData);

        return $this->postJournalAction->execute($draftJournal);
    }
}
