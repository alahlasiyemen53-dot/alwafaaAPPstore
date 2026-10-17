<?php

namespace App\Actions;

use App\DTOs\ReverseJournalEntryData;
use App\Enums\CustomerWalletEntryType;
use App\Models\CustomerWalletAccount;
use App\Models\CustomerWalletEntry;
use App\Services\AuditLogger;
use App\Services\CustomerWalletBalanceQuery;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseCustomerWalletEntryAction
{
    public function __construct(
        private readonly ReverseJournalEntryAction $reverseJournalAction,
        private readonly CustomerWalletBalanceQuery $balanceQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerWalletEntry $entry, string $reason, int $userId): CustomerWalletEntry
    {
        return DB::transaction(function () use ($entry, $reason, $userId): CustomerWalletEntry {
            /** @var CustomerWalletEntry $lockedEntry */
            $lockedEntry = CustomerWalletEntry::query()
                ->lockForUpdate()
                ->with(['account', 'journalEntry'])
                ->findOrFail($entry->id);

            if ($lockedEntry->type === CustomerWalletEntryType::Reversal) {
                throw new DomainException('لا يمكن عكس حركة عكسية.');
            }

            $alreadyReversed = CustomerWalletEntry::query()
                ->where('reversal_of_id', $lockedEntry->id)
                ->exists();

            if ($alreadyReversed) {
                throw new DomainException('تم عكس هذه الحركة مسبقاً ولا يمكن عكسها مرة أخرى.');
            }

            /** @var CustomerWalletAccount $lockedAccount */
            $lockedAccount = CustomerWalletAccount::query()
                ->lockForUpdate()
                ->findOrFail($lockedEntry->customer_wallet_account_id);

            if (! $lockedAccount->isActive()) {
                throw new DomainException('محفظة العميل معلقة ولا يمكن عكس حركاتها.');
            }

            // Reversing a credit reduces balance
            if ($lockedEntry->type->isCredit()) {
                $currentBalance = $this->balanceQuery->getBalance($lockedAccount);
                if (bccomp($currentBalance, $lockedEntry->amount, 4) < 0) {
                    throw new DomainException(sprintf('رصيد المحفظة المتاح (%s) غير كافٍ لعكس هذه الحركة بقيمة (%s).', $currentBalance, $lockedEntry->amount));
                }
            }

            // Reverse GL journal entry if attached
            $reversalJournal = null;
            if ($lockedEntry->journal_entry_id !== null && $lockedEntry->journalEntry !== null) {
                $reversalData = new ReverseJournalEntryData(
                    reason: sprintf('عكس حركة المحفظة رقم %s: %s', $lockedEntry->entry_number, $reason),
                    reversalDate: now()->toDateString(),
                );

                $reversalJournal = $this->reverseJournalAction->execute($lockedEntry->journalEntry, $reversalData);
            }

            $entryNumber = sprintf('WAL-REV-%d-%s', $lockedAccount->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerWalletEntry $reversalEntry */
            $reversalEntry = CustomerWalletEntry::create([
                'customer_wallet_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => CustomerWalletEntryType::Reversal,
                'amount' => $lockedEntry->amount,
                'currency_id' => $lockedEntry->currency_id,
                'source_type' => 'CustomerWalletEntry',
                'source_id' => $lockedEntry->id,
                'source_event' => 'WALLET_ENTRY_REVERSED',
                'description' => sprintf('عكس الحركة رقم %s: %s', $lockedEntry->entry_number, $reason),
                'journal_entry_id' => $reversalJournal?->id,
                'reversal_of_id' => $lockedEntry->id,
                'posted_at' => now(),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            $balanceAfter = $this->balanceQuery->getBalance($lockedAccount);

            $this->auditLogger->record('admin.customer_wallet.reversed', $reversalEntry, metadata: [
                'original_entry_id' => $lockedEntry->id,
                'original_entry_number' => $lockedEntry->entry_number,
                'reversal_entry_number' => $entryNumber,
                'reason' => $reason,
                'amount' => $lockedEntry->amount,
                'balance_after' => $balanceAfter,
            ]);

            return $reversalEntry;
        });
    }
}
