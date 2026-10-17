<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\CreateDraftTreasuryTransactionAction;
use App\DTOs\TreasuryTransactionData;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProviderCode;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\CustomerPaymentMethod;
use App\Models\ElectronicWalletAccount;
use App\Models\Order;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryPostingService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Posts a company electronic-wallet receipt when a customer order is paid online.
 * Driver/COD clearing (1160) is never touched.
 */
final class PostOnlineWalletPaymentAction
{
    public const EVENT_KEY = 'ONLINE_WALLET_PAYMENT_CAPTURED';

    public const CLEARING_ACCOUNT_CODE = '1170';

    public function __construct(
        private readonly CreateDraftTreasuryTransactionAction $createDraftTreasuryTransaction,
        private readonly TreasuryPostingService $treasuryPostingService,
    ) {}

    public function execute(Order $order): ?TreasuryTransaction
    {
        if ($order->payment_status !== OrderPaymentStatus::Paid) {
            return null;
        }

        $methodCode = (string) ($order->payment_method_code ?? '');
        if ($methodCode === '' || $methodCode === PaymentProviderCode::Cod->value) {
            return null;
        }

        return DB::transaction(function () use ($order, $methodCode): ?TreasuryTransaction {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->treasury_transaction_id !== null) {
                return TreasuryTransaction::query()->find($lockedOrder->treasury_transaction_id);
            }

            $reference = 'ONLINE-ORDER-'.$lockedOrder->id;
            $existing = TreasuryTransaction::query()
                ->where('reference_number', $reference)
                ->first();
            if ($existing !== null) {
                $lockedOrder->update(['treasury_transaction_id' => $existing->id]);

                return $existing;
            }

            $method = CustomerPaymentMethod::query()
                ->with('electronicWalletAccount')
                ->where('code', $methodCode)
                ->first();

            if ($method === null) {
                throw new DomainException('طريقة الدفع غير معرّفة للترحيل المالي.');
            }

            if ($method->provider_code === PaymentProviderCode::Cod) {
                return null;
            }

            $wallet = $method->electronicWalletAccount;
            if ($wallet === null || ! $wallet->is_active) {
                throw new DomainException(
                    'طريقة الدفع «'.$method->name_ar.'» غير مربوطة بمحفظة إلكترونية نشطة في الخزينة.'
                );
            }

            $offsetAccountId = $this->resolveClearingAccountId();

            $amount = (float) $lockedOrder->total;
            if ($amount <= 0) {
                throw new DomainException('مبلغ الطلب غير صالح للترحيل المالي.');
            }

            $description = sprintf(
                'قبض محفظة إلكترونية للطلب %s (%s)',
                $lockedOrder->order_number,
                $method->name_ar
            );

            $draft = $this->createDraftTreasuryTransaction->execute(new TreasuryTransactionData(
                transactionType: TreasuryTransactionType::WalletReceipt,
                accountType: ElectronicWalletAccount::class,
                accountId: $wallet->id,
                currencyId: (int) $lockedOrder->currency_id,
                amount: $amount,
                description: $description,
                offsetAccountId: $offsetAccountId,
                counterpartyType: null,
                counterpartyId: null,
                referenceNumber: $reference,
            ));

            $posted = $this->treasuryPostingService->post($draft);

            $lockedOrder->update(['treasury_transaction_id' => $posted->id]);

            return $posted;
        });
    }

    /**
     * Prefer active mapping credit; otherwise ensure mapping from GL 1170.
     */
    private function resolveClearingAccountId(): int
    {
        $mapping = AccountingMapping::query()
            ->where('event_key', self::EVENT_KEY)
            ->where('is_active', true)
            ->first();

        if ($mapping !== null && $mapping->credit_account_id !== null) {
            return (int) $mapping->credit_account_id;
        }

        $clearing = Account::query()
            ->where('code', self::CLEARING_ACCOUNT_CODE)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->first();

        if ($clearing === null) {
            throw new DomainException('تعيين المحاسبة لقبض المحفظة الإلكترونية غير مُعد.');
        }

        $cashPlaceholder = Account::query()->where('code', '1110')->first();

        AccountingMapping::query()->updateOrCreate(
            ['event_key' => self::EVENT_KEY],
            [
                'name_ar' => 'قبض محفظة إلكترونية عند دفع طلب العميل',
                'debit_account_id' => $cashPlaceholder?->id ?? $clearing->id,
                'credit_account_id' => $clearing->id,
                'is_active' => true,
                'description' => 'إثبات دخول النقدية إلى محفظة الشركة الإلكترونية (المدين يُستبدل بحساب المحفظة الفعلي عند الترحيل)',
            ]
        );

        return (int) $clearing->id;
    }
}
