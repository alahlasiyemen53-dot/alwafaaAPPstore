<?php

namespace App\Services;

use App\DTOs\JournalEntryLineData;
use App\Models\Account;
use App\Models\Currency;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class JournalBalanceService
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * Validate lines invariants and compute balanced totals in base currency.
     *
     * @param  array<JournalEntryLineData|array<string, mixed>>  $lines
     * @return array{
     *     total_debit: string,
     *     total_credit: string,
     *     prepared_lines: array<int, array<string, mixed>>
     * }
     */
    public function validateAndPrepareLines(array $lines, Currency $entryCurrency, float|string $entryExchangeRate): array
    {
        if (count($lines) < 2) {
            throw new UnprocessableEntityHttpException('يجب أن يحتوي القيد المحاسبي على سطرين على الأقل.');
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $totalDebit = '0';
        $totalCredit = '0';
        $preparedLines = [];
        $order = 1;

        foreach ($lines as $line) {
            $data = $line instanceof JournalEntryLineData ? [
                'account_id' => $line->accountId,
                'description' => $line->description,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'transaction_currency_id' => $line->transactionCurrencyId,
                'transaction_amount' => $line->transactionAmount,
                'exchange_rate_snapshot' => $line->exchangeRateSnapshot,
                'base_currency_amount' => $line->baseCurrencyAmount,
                'party_type' => $line->partyType,
                'party_id' => $line->partyId,
                'line_order' => $line->lineOrder,
                'treasury_currency_account_id' => $line->treasuryCurrencyAccountId,
            ] : $line;

            $accountId = (int) $data['account_id'];
            $account = Account::find($accountId);
            if (! $account) {
                throw new UnprocessableEntityHttpException("الحساب ذو المعرف {$accountId} غير موجود.");
            }

            if (! $account->is_active) {
                throw new UnprocessableEntityHttpException("الحساب {$account->name_ar} معطل ولا يمكن تسجيل قيود عليه.");
            }

            if (! $account->is_postable) {
                throw new UnprocessableEntityHttpException("الحساب {$account->name_ar} تجميعي/رئيسي ولا يقبل ترحيل حركات مباشرة.");
            }

            $debit = number_format((float) ($data['debit'] ?? 0), 4, '.', '');
            $credit = number_format((float) ($data['credit'] ?? 0), 4, '.', '');

            $isDebitPositive = bccomp($debit, '0', 4) > 0;
            $isCreditPositive = bccomp($credit, '0', 4) > 0;

            if (($isDebitPositive && $isCreditPositive) || (! $isDebitPositive && ! $isCreditPositive)) {
                throw new UnprocessableEntityHttpException('كل سطر في القيد يجب أن يحتوي إما على مدين موجب أو دائن موجب حصراً.');
            }

            $txCurrencyId = (int) ($data['transaction_currency_id'] ?? $entryCurrency->id);
            $txCurrency = Currency::find($txCurrencyId) ?? $entryCurrency;

            // Resolve exchange rate to base currency
            if ($txCurrency->id === $baseCurrency->id) {
                $lineRate = '1.00000000';
            } elseif (! empty($data['exchange_rate_snapshot']) && bccomp((string) $data['exchange_rate_snapshot'], '0', 8) > 0) {
                $lineRate = number_format((float) $data['exchange_rate_snapshot'], 8, '.', '');
            } else {
                $rateInfo = $this->currencyConverter->resolveRate($txCurrency, $baseCurrency);
                if ($rateInfo === null) {
                    throw new UnprocessableEntityHttpException("لا يوجد سعر صرف معرف بين {$txCurrency->code} والعملة الأساسية.");
                }
                $lineRate = $rateInfo['rate'];
            }

            $txAmount = $isDebitPositive ? $debit : $credit;
            // Base currency amount calculation
            $baseAmount = number_format((float) bcmul($txAmount, $lineRate, 4), 4, '.', '');

            $baseDebit = $isDebitPositive ? $baseAmount : '0.0000';
            $baseCredit = $isCreditPositive ? $baseAmount : '0.0000';

            $totalDebit = bcadd($totalDebit, $baseDebit, 4);
            $totalCredit = bcadd($totalCredit, $baseCredit, 4);

            $preparedLines[] = [
                'account_id' => $account->id,
                'description' => $data['description'] ?? null,
                'debit' => $baseDebit,
                'credit' => $baseCredit,
                'transaction_currency_id' => $txCurrency->id,
                'transaction_amount' => $txAmount,
                'exchange_rate_snapshot' => $lineRate,
                'base_currency_amount' => $baseAmount,
                'party_type' => $data['party_type'] ?? null,
                'party_id' => $data['party_id'] ?? null,
                'line_order' => $data['line_order'] ?? $order++,
                'treasury_currency_account_id' => ! empty($data['treasury_currency_account_id'])
                    ? (int) $data['treasury_currency_account_id']
                    : null,
            ];
        }

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'prepared_lines' => $preparedLines,
        ];
    }
}
