<?php

namespace App\Actions;

use App\DTOs\TreasuryTransferData;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\TreasuryTransaction;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateTreasuryTransferAction
{
    public function __construct(
        private readonly GenerateTreasuryTransactionNumberAction $numberGenerator,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(TreasuryTransferData $data): TreasuryTransaction
    {
        $amount = (float) $data->amount;
        if ($amount <= 0) {
            throw new UnprocessableEntityHttpException('يجب أن يكون مبلغ التحويل أكبر من الصفر.');
        }

        if ($data->sourceType === $data->destinationType && $data->sourceId === $data->destinationId) {
            throw new UnprocessableEntityHttpException('لا يمكن التحويل المالي إلى نفس الحساب.');
        }

        $sourceClass = $data->sourceType;
        $destClass = $data->destinationType;

        if (! class_exists($sourceClass) || ! class_exists($destClass)) {
            throw new UnprocessableEntityHttpException('نوع حساب الخزينة غير صالح.');
        }

        $sourceAccount = $sourceClass::with(['currency', 'currencyAccounts'])->findOrFail($data->sourceId);
        $destAccount = $destClass::with(['currency', 'currencyAccounts'])->findOrFail($data->destinationId);

        if (! $sourceAccount->is_active) {
            throw new UnprocessableEntityHttpException('حساب المصدر معطل ولا يمكن التحويل منه.');
        }

        if (! $destAccount->is_active) {
            throw new UnprocessableEntityHttpException('حساب الوجهة معطل ولا يمكن التحويل إليه.');
        }

        $sourceCurrencyIds = $sourceAccount->currencyAccounts
            ->where('is_active', true)
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($sourceCurrencyIds === []) {
            $sourceCurrencyIds = [(int) $sourceAccount->currency_id];
        }

        $destCurrencyIds = $destAccount->currencyAccounts
            ->where('is_active', true)
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($destCurrencyIds === []) {
            $destCurrencyIds = [(int) $destAccount->currency_id];
        }

        $commonCurrencyIds = array_values(array_intersect($sourceCurrencyIds, $destCurrencyIds));
        if ($commonCurrencyIds === []) {
            throw new UnprocessableEntityHttpException('لا توجد عملة مشتركة مفتوحة بين حساب المصدر والوجهة.');
        }

        $transferCurrencyId = in_array((int) $sourceAccount->currency_id, $commonCurrencyIds, true)
            ? (int) $sourceAccount->currency_id
            : $commonCurrencyIds[0];

        $sourceTca = $sourceAccount->currencyAccounts
            ->first(fn ($ca) => (int) $ca->currency_id === $transferCurrencyId && $ca->is_active);
        $destTca = $destAccount->currencyAccounts
            ->first(fn ($ca) => (int) $ca->currency_id === $transferCurrencyId && $ca->is_active);

        if ($sourceTca === null || $destTca === null) {
            throw new UnprocessableEntityHttpException('تعذر ربط التحويل بحساب العملة التشغيلي للمصدر أو الوجهة.');
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $sourceCurrency = $sourceAccount->currencyAccounts
            ->first(fn ($ca) => (int) $ca->currency_id === $transferCurrencyId)
            ?->currency
            ?? $sourceAccount->currency;

        $rate = '1.00000000';
        if ($sourceCurrency && (int) $sourceCurrency->id !== (int) $baseCurrency->id) {
            if ($data->exchangeRateSnapshot !== null && (float) $data->exchangeRateSnapshot > 0) {
                $rate = number_format((float) $data->exchangeRateSnapshot, 8, '.', '');
            } else {
                $rateInfo = $this->currencyConverter->resolveRate($sourceCurrency, $baseCurrency);
                if ($rateInfo === null) {
                    throw new UnprocessableEntityHttpException("لا يوجد سعر صرف معرف بين {$sourceCurrency->code} والعملة الأساسية.");
                }
                $rate = $rateInfo['rate'];
            }
        }

        $amountStr = number_format($amount, 4, '.', '');
        $baseAmount = number_format((float) bcmul($amountStr, $rate, 4), 4, '.', '');

        $trxNumber = $this->numberGenerator->execute();
        $userId = auth()->id() > 0 ? auth()->id() : null;

        $transaction = TreasuryTransaction::create([
            'transaction_number' => $trxNumber,
            'transaction_type' => TreasuryTransactionType::Transfer,
            'status' => TreasuryTransactionStatus::Draft,
            'account_type' => $data->sourceType,
            'account_id' => $data->sourceId,
            'treasury_currency_account_id' => $sourceTca->id,
            'destination_type' => $data->destinationType,
            'destination_id' => $data->destinationId,
            'destination_currency_account_id' => $destTca->id,
            'currency_id' => $transferCurrencyId,
            'amount' => $amountStr,
            'exchange_rate_snapshot' => $rate,
            'base_currency_amount' => $baseAmount,
            'reference_number' => $data->referenceNumber,
            'description' => $data->description,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->auditLogger->record('admin.treasury_transfer.created', $transaction, metadata: [
            'transaction_number' => $transaction->transaction_number,
            'amount' => $transaction->amount,
            'source_type' => $transaction->account_type,
            'dest_type' => $transaction->destination_type,
            'currency_id' => $transferCurrencyId,
        ]);

        return $transaction;
    }
}
