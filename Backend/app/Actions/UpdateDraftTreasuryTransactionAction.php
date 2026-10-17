<?php

namespace App\Actions;

use App\DTOs\TreasuryTransactionData;
use App\Models\Currency;
use App\Models\TreasuryTransaction;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UpdateDraftTreasuryTransactionAction
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(TreasuryTransaction $transaction, TreasuryTransactionData $data): TreasuryTransaction
    {
        if (! $transaction->isDraft()) {
            throw new ConflictHttpException('لا يمكن تعديل حركة خزينة مرحلة أو معكوسة.');
        }

        $amount = (float) $data->amount;
        if ($amount <= 0) {
            throw new UnprocessableEntityHttpException('يجب أن يكون مبلغ الحركة أكبر من الصفر.');
        }

        $accountClass = $data->accountType;
        if (! class_exists($accountClass)) {
            throw new UnprocessableEntityHttpException('نوع حساب الخزينة غير صالح.');
        }

        $account = $accountClass::with('currencyAccounts')->findOrFail($data->accountId);
        if (! $account->is_active) {
            throw new UnprocessableEntityHttpException('حساب الخزينة المحدد غير نشط.');
        }

        $currencyAccount = $account->currencyAccounts
            ->first(fn ($ca) => (int) $ca->currency_id === (int) $data->currencyId && $ca->is_active);

        if ($currencyAccount === null) {
            throw new UnprocessableEntityHttpException('العملة المحددة غير مفتوحة على حساب الخزينة هذا.');
        }

        $currency = Currency::findOrFail($data->currencyId);
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        $rate = '1.00000000';
        if ($currency->id !== $baseCurrency->id) {
            if ($data->exchangeRateSnapshot !== null && (float) $data->exchangeRateSnapshot > 0) {
                $rate = number_format((float) $data->exchangeRateSnapshot, 8, '.', '');
            } else {
                $rateInfo = $this->currencyConverter->resolveRate($currency, $baseCurrency);
                if ($rateInfo === null) {
                    throw new UnprocessableEntityHttpException("لا يوجد سعر صرف معرف بين {$currency->code} والعملة الأساسية.");
                }
                $rate = $rateInfo['rate'];
            }
        }

        $amountStr = number_format($amount, 4, '.', '');
        $baseAmount = number_format((float) bcmul($amountStr, $rate, 4), 4, '.', '');

        $userId = auth()->id() > 0 ? auth()->id() : null;

        $transaction->update([
            'transaction_type' => $data->transactionType,
            'account_type' => $data->accountType,
            'account_id' => $data->accountId,
            'treasury_currency_account_id' => $currencyAccount->id,
            'currency_id' => $data->currencyId,
            'amount' => $amountStr,
            'exchange_rate_snapshot' => $rate,
            'base_currency_amount' => $baseAmount,
            'offset_account_id' => $data->offsetAccountId,
            'counterparty_type' => $data->counterpartyType,
            'counterparty_id' => $data->counterpartyId,
            'reference_number' => $data->referenceNumber,
            'description' => $data->description,
            'updated_by' => $userId,
        ]);

        $this->auditLogger->record('admin.treasury_transaction.draft_updated', $transaction, metadata: [
            'transaction_number' => $transaction->transaction_number,
            'type' => $transaction->transaction_type->value,
            'amount' => $transaction->amount,
            'treasury_currency_account_id' => $currencyAccount->id,
        ]);

        return $transaction;
    }
}
