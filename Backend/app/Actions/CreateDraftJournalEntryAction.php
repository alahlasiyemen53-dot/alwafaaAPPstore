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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateDraftJournalEntryAction
{
    public function __construct(
        private readonly GenerateJournalEntryNumberAction $numberGenerator,
        private readonly JournalBalanceService $balanceService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateJournalEntryData $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $period = AccountingPeriod::find($data->accountingPeriodId);
            if (! $period) {
                throw new UnprocessableEntityHttpException('الفترة المحاسبية المحددة غير موجودة.');
            }

            if ($period->status !== AccountingPeriodStatus::Open) {
                throw new UnprocessableEntityHttpException('لا يمكن إنشاء قيود في فترة محاسبية مغلقة.');
            }

            $currency = Currency::findOrFail($data->currencyId);
            $entryDate = Carbon::parse($data->entryDate);

            $validationResult = $this->balanceService->validateAndPrepareLines(
                $data->lines,
                $currency,
                $data->exchangeRateSnapshot
            );

            $entryNumber = $this->numberGenerator->execute($entryDate);
            $user = auth()->user();
            $userId = $user instanceof \App\Models\User ? (int) $user->id : null;

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'accounting_period_id' => $period->id,
                'entry_date' => $entryDate->toDateString(),
                'status' => JournalEntryStatus::Draft,
                'description' => $data->description,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'source_event' => $data->sourceEvent,
                'currency_id' => $currency->id,
                'exchange_rate_snapshot' => $data->exchangeRateSnapshot,
                'total_debit' => $validationResult['total_debit'],
                'total_credit' => $validationResult['total_credit'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($validationResult['prepared_lines'] as $preparedLine) {
                $preparedLine['journal_entry_id'] = $entry->id;
                JournalEntryLine::create($preparedLine);
            }

            $this->auditLogger->record('admin.journal_entry.draft_created', $entry, metadata: [
                'entry_number' => $entry->entry_number,
                'total_debit' => $entry->total_debit,
                'total_credit' => $entry->total_credit,
            ]);

            return $entry->fresh(['accountingPeriod', 'currency', 'lines.account']);
        });
    }
}
