<?php

namespace App\Actions;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PostJournalEntryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry): JournalEntry {
            /** @var JournalEntry $lockedEntry */
            $lockedEntry = JournalEntry::query()
                ->lockForUpdate()
                ->with(['accountingPeriod', 'lines.account'])
                ->findOrFail($entry->id);

            if ($lockedEntry->status === JournalEntryStatus::Posted) {
                // Idempotent: if already posted, return safely
                return $lockedEntry;
            }

            if ($lockedEntry->status === JournalEntryStatus::Reversed) {
                throw new ConflictHttpException('لا يمكن ترحيل قيد معكوس.');
            }

            // Lock the accounting period row itself (not just the pre-loaded relation) to close
            // the race with a concurrent CloseAccountingPeriodAction committing between our read
            // and our write.
            $lockedPeriod = $lockedEntry->accounting_period_id
                ? AccountingPeriod::query()->lockForUpdate()->find($lockedEntry->accounting_period_id)
                : null;

            if ($lockedPeriod?->status !== AccountingPeriodStatus::Open) {
                throw new UnprocessableEntityHttpException('لا يمكن ترحيل القيد في فترة محاسبية مغلقة.');
            }

            if ($lockedEntry->lines->count() < 2) {
                throw new UnprocessableEntityHttpException('لا يمكن ترحيل قيد يحتوي على أقل من سطرين.');
            }

            // Verify each account is active and postable
            foreach ($lockedEntry->lines as $line) {
                $account = $line->account;
                if (! $account) {
                    throw new UnprocessableEntityHttpException('أحد أسطر القيد مرتبط بحساب غير موجود.');
                }
                if (! $account->is_active) {
                    throw new UnprocessableEntityHttpException("الحساب {$account->name_ar} معطل ولا يمكن ترحيل قيود عليه.");
                }
                if (! $account->is_postable) {
                    throw new UnprocessableEntityHttpException("الحساب {$account->name_ar} حساب تجميعي/رئيسي ولا يقبل ترحيل قيود عليه.");
                }
            }

            // Enforce double entry invariant (SUM debit == SUM credit)
            $totalDebit = '0';
            $totalCredit = '0';
            foreach ($lockedEntry->lines as $line) {
                $totalDebit = bcadd($totalDebit, (string) $line->debit, 4);
                $totalCredit = bcadd($totalCredit, (string) $line->credit, 4);
            }

            if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
                throw new UnprocessableEntityHttpException("القيد غير متوازن: إجمالي المدين ({$totalDebit}) لا يتطابق مع إجمالي الدائن ({$totalCredit}).");
            }

            if (bccomp($totalDebit, '0', 4) <= 0) {
                throw new UnprocessableEntityHttpException('إجمالي مبالغ القيد يجب أن يكون أكبر من الصفر.');
            }

            $user = auth()->user();
            $userId = $user instanceof \App\Models\User ? (int) $user->id : null;

            $lockedEntry->update([
                'status' => JournalEntryStatus::Posted,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => now(),
                'posted_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.journal_entry.posted', $lockedEntry, metadata: [
                'entry_number' => $lockedEntry->entry_number,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            return $lockedEntry->fresh(['accountingPeriod', 'currency', 'lines.account', 'postedBy']);
        });
    }
}
