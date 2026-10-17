<?php

namespace App\Actions;

use App\DTOs\CustomerWalletAdjustmentData;
use App\Enums\CustomerWalletAccountStatus;
use App\Enums\CustomerWalletEntryType;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Services\AuditLogger;
use App\Services\CustomerWalletAccountingService;
use App\Services\CustomerWalletBalanceQuery;
use App\Support\Decimal;
use App\Support\RaceSafeFirstOrCreate;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreditCustomerWalletAction
{
    public function __construct(
        private readonly CustomerWalletAccountingService $accountingService,
        private readonly CustomerWalletBalanceQuery $balanceQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerWalletAdjustmentData $data): CustomerWalletEntry
    {
        return DB::transaction(function () use ($data): CustomerWalletEntry {
            $amount = Decimal::normalize($data->amount);
            if (bccomp($amount, '0.0000', 4) <= 0) {
                throw new DomainException('مبلغ الإيداع يجب أن يكون أكبر من الصفر.');
            }

            $account = RaceSafeFirstOrCreate::resolve(
                CustomerWalletAccount::class,
                [
                    'customer_id' => $data->customerId,
                    'currency_id' => $data->currencyId,
                ],
                ['status' => CustomerWalletAccountStatus::Active]
            );

            $lockedAccount = CustomerWalletAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (! $lockedAccount->isActive()) {
                throw new DomainException('محفظة العميل معلقة ولا يمكن إجراء عمليات إيداع عليها.');
            }

            $userId = (int) ($data->createdBy ?? 0);
            $journalEntry = $this->accountingService->postManualCredit(
                account: $lockedAccount,
                amount: $amount,
                reason: $data->reason,
                userId: $userId
            );

            $entryNumber = sprintf('WAL-CR-%d-%s', $lockedAccount->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerWalletEntry $entry */
            $entry = CustomerWalletEntry::create([
                'customer_wallet_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => CustomerWalletEntryType::ManualCredit,
                'amount' => $amount,
                'currency_id' => $data->currencyId,
                'source_type' => 'ManualAdjustment',
                'source_id' => $userId > 0 ? $userId : 1,
                'source_event' => 'ADMIN_MANUAL_CREDIT',
                'description' => $data->reason,
                'journal_entry_id' => $journalEntry->id,
                'posted_at' => now(),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            $balanceAfter = $this->balanceQuery->getBalance($lockedAccount);

            $this->auditLogger->record('admin.customer_wallet.credited', $entry, metadata: [
                'customer_id' => $data->customerId,
                'currency_id' => $data->currencyId,
                'amount' => $amount,
                'reason' => $data->reason,
                'entry_number' => $entryNumber,
                'journal_entry_id' => $journalEntry->id,
                'balance_after' => $balanceAfter,
            ]);

            return $entry;
        });
    }
}
