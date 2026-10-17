<?php

namespace App\Actions;

use App\DTOs\ReverseJournalEntryData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReverseJournalEntryAction
{
    public function __construct(
        private readonly GenerateJournalEntryNumberAction $numberGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(JournalEntry $entry, ReverseJournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data): JournalEntry {
            /** @var JournalEntry $lockedEntry */
            $lockedEntry = JournalEntry::query()
                ->lockForUpdate()
                ->with(['lines', 'accountingPeriod'])
                ->findOrFail($entry->id);

            if ($lockedEntry->status !== JournalEntryStatus::Posted) {
                throw new ConflictHttpException('يمكن فقط عكس القيود المرحلة.');
            }

            if ($lockedEntry->reversed_at !== null || $lockedEntry->reversal_of_id !== null) {
                throw new ConflictHttpException('هذا القيد تم عكسه مسبقاً أو أنه قيد عكسي لقيد آخر.');
            }

            // Check existing reversal entry
            $alreadyHasReversal = JournalEntry::query()
                ->where('reversal_of_id', $lockedEntry->id)
                ->exists();

            if ($alreadyHasReversal) {
                throw new ConflictHttpException('يوجد قيد عكسي مسجل بالفعل لهذا القيد.');
            }

            $reversalDate = Carbon::parse($data->reversalDate);

            // Determine target period
            $targetPeriodId = $data->accountingPeriodId ?? $lockedEntry->accounting_period_id;
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($targetPeriodId);

            if ($period->status !== AccountingPeriodStatus::Open) {
                throw new UnprocessableEntityHttpException('الفترة المحاسبية المستهدفة لعكس القيد مغلقة.');
            }

            $entryNumber = $this->numberGenerator->execute($reversalDate);
            $userId = auth()->id() > 0 ? auth()->id() : null;

            // Create reversal entry with swapped debit and credit
            $reversalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'accounting_period_id' => $period->id,
                'entry_date' => $reversalDate->toDateString(),
                'status' => JournalEntryStatus::Posted,
                'description' => 'قيد عكسي للقيد رقم '.$lockedEntry->entry_number.' - سبب العكس: '.$data->reason,
                'source_type' => $lockedEntry->source_type,
                'source_id' => $lockedEntry->source_id,
                'source_event' => 'REVERSED',
                'currency_id' => $lockedEntry->currency_id,
                'exchange_rate_snapshot' => $lockedEntry->exchange_rate_snapshot,
                'total_debit' => $lockedEntry->total_credit, // Swapped
                'total_credit' => $lockedEntry->total_debit, // Swapped
                'posted_at' => now(),
                'posted_by' => $userId,
                'reversal_of_id' => $lockedEntry->id,
                'reversal_reason' => $data->reason,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Invert lines (debit becomes credit, credit becomes debit)
            foreach ($lockedEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'treasury_currency_account_id' => $line->treasury_currency_account_id,
                    'description' => 'عكس سطر: '.($line->description ?? ''),
                    'debit' => $line->credit, // Swapped
                    'credit' => $line->debit, // Swapped
                    'transaction_currency_id' => $line->transaction_currency_id,
                    'transaction_amount' => $line->transaction_amount,
                    'exchange_rate_snapshot' => $line->exchange_rate_snapshot,
                    'base_currency_amount' => $line->base_currency_amount,
                    'party_type' => $line->party_type,
                    'party_id' => $line->party_id,
                    'line_order' => $line->line_order,
                ]);
            }

            // Mark original entry as reversed
            $lockedEntry->update([
                'status' => JournalEntryStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $data->reason,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.journal_entry.reversed', $lockedEntry, metadata: [
                'original_entry_number' => $lockedEntry->entry_number,
                'reversal_entry_number' => $reversalEntry->entry_number,
                'reason' => $data->reason,
            ]);

            return $reversalEntry->fresh(['accountingPeriod', 'currency', 'lines.account', 'reversalOf']);
        });
    }
}
