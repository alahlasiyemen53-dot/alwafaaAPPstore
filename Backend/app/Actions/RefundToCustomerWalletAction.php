<?php

namespace App\Actions;

use App\DTOs\CustomerWalletRefundData;
use App\Enums\CustomerWalletAccountStatus;
use App\Enums\CustomerWalletEntryType;
use App\Enums\SalesReturnStatus;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Models\SalesReturn;
use App\Services\AuditLogger;
use App\Services\CustomerWalletAccountingService;
use App\Services\CustomerWalletBalanceQuery;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RefundToCustomerWalletAction
{
    public function __construct(
        private readonly CustomerWalletAccountingService $accountingService,
        private readonly CustomerWalletBalanceQuery $balanceQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerWalletRefundData $data): CustomerWalletEntry
    {
        return DB::transaction(function () use ($data): CustomerWalletEntry {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::query()
                ->with(['order.customer', 'currency'])
                ->lockForUpdate()
                ->findOrFail($data->salesReturnId);

            if ($salesReturn->status !== SalesReturnStatus::Posted) {
                throw new DomainException('لا يمكن رد قيمة المرتجع إلا للمرتجعات المعتمدة (Posted).');
            }

            $existingEntry = CustomerWalletEntry::query()
                ->where('source_type', 'SalesReturn')
                ->where('source_id', $salesReturn->id)
                ->where('source_event', 'SALES_RETURN_REFUND')
                ->first();

            if ($existingEntry !== null) {
                throw new DomainException('تم رد قيمة هذا المرتجع مسبقاً إلى محفظة العميل ولا يمكن الرد مرة أخرى.');
            }

            $customerId = (int) $salesReturn->order?->customer_id;
            if ($customerId <= 0) {
                throw new DomainException('العميل المرتبط بطلب هذا المرتجع غير موجود أو غير صالح.');
            }

            $amount = Decimal::normalize((string) $salesReturn->total);
            if (bccomp($amount, '0.0000', 4) <= 0) {
                throw new DomainException('مبلغ الاسترداد يجب أن يكون أكبر من الصفر.');
            }

            /** @var CustomerWalletAccount $account */
            $account = CustomerWalletAccount::firstOrCreate(
                [
                    'customer_id' => $customerId,
                    'currency_id' => $salesReturn->currency_id,
                ],
                ['status' => CustomerWalletAccountStatus::Active]
            );

            /** @var CustomerWalletAccount $lockedAccount */
            $lockedAccount = CustomerWalletAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (! $lockedAccount->isActive()) {
                throw new DomainException('محفظة العميل معلقة ولا يمكن إيداع مبالغ الاسترداد إليها.');
            }

            $reason = $data->reason ?: sprintf('استرداد قيمة مرتجع المبيعات رقم %s', $salesReturn->return_number);
            $userId = (int) ($data->refundedBy ?? 0);

            $journalEntry = $this->accountingService->postRefundCredit(
                account: $lockedAccount,
                amount: $amount,
                reason: $reason,
                userId: $userId,
                salesReturnId: $salesReturn->id
            );

            $entryNumber = sprintf('WAL-REF-%d-%s', $lockedAccount->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerWalletEntry $entry */
            $entry = CustomerWalletEntry::create([
                'customer_wallet_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => CustomerWalletEntryType::RefundCredit,
                'amount' => $amount,
                'currency_id' => $salesReturn->currency_id,
                'source_type' => 'SalesReturn',
                'source_id' => $salesReturn->id,
                'source_event' => 'SALES_RETURN_REFUND',
                'description' => $reason,
                'journal_entry_id' => $journalEntry->id,
                'posted_at' => now(),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            $balanceAfter = $this->balanceQuery->getBalance($lockedAccount);

            $this->auditLogger->record('admin.customer_wallet.refunded', $entry, metadata: [
                'customer_id' => $customerId,
                'currency_id' => $salesReturn->currency_id,
                'sales_return_id' => $salesReturn->id,
                'return_number' => $salesReturn->return_number,
                'amount' => $amount,
                'reason' => $reason,
                'entry_number' => $entryNumber,
                'journal_entry_id' => $journalEntry->id,
                'balance_after' => $balanceAfter,
            ]);

            return $entry;
        });
    }
}
