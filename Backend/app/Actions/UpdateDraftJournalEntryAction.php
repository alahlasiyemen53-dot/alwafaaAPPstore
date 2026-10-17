<?php

namespace App\Actions;

use App\DTOs\CreateJournalEntryData;
use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\AuditLogger;
use App\Services\JournalBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UpdateDraftJournalEntryAction
{
    public function __construct(
        private readonly JournalBalanceService $balanceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(JournalEntry $entry, CreateJournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data): JournalEntry {
            /** @var JournalEntry $lockedEntry */
            $lockedEntry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($lockedEntry->status !== JournalEntryStatus::Draft) {
                throw new ConflictHttpException('لا يمكن تعديل قيد مرحل أو معكوس.');
            }

            $period = AccountingPeriod::find($data->accountingPeriodId);
            if (! $period) {
                throw new UnprocessableEntityHttpException('الفترة المحاسبية المحددة غير موجودة.');
            }

            if ($period->status !== AccountingPeriodStatus::Open) {
                throw new UnprocessableEntityHttpException('لا يمكن ربط القيد بفترة محاسبية مغلقة.');
            }

            $currency = Currency::findOrFail($data->currencyId);
            $entryDate = Carbon::parse($data->entryDate);

            $validationResult = $this->balanceService->validateAndPrepareLines(
                $data->lines,
                $currency,
                $data->exchangeRateSnapshot
            );

            $userId = auth()->id() > 0 ? auth()->id() : null;

            $lockedEntry->update([
                'accounting_period_id' => $period->id,
                'entry_date' => $entryDate->toDateString(),
                'description' => $data->description,
                'currency_id' => $currency->id,
                'exchange_rate_snapshot' => $data->exchangeRateSnapshot,
                'total_debit' => $validationResult['total_debit'],
                'total_credit' => $validationResult['total_credit'],
                'updated_by' => $userId,
            ]);

            // Replace lines
            $lockedEntry->lines()->delete();

            foreach ($validationResult['prepared_lines'] as $preparedLine) {
                $preparedLine['journal_entry_id'] = $lockedEntry->id;
                JournalEntryLine::create($preparedLine);
            }

            $this->auditLogger->record('admin.journal_entry.draft_updated', $lockedEntry, metadata: [
                'entry_number' => $lockedEntry->entry_number,
                'total_debit' => $lockedEntry->total_debit,
                'total_credit' => $lockedEntry->total_credit,
            ]);

            return $lockedEntry->fresh(['accountingPeriod', 'currency', 'lines.account']);
        });
    }
}
