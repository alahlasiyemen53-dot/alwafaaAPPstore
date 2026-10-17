<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ReverseJournalEntryData;
use App\Enums\DriverCodSettlementStatus;
use App\Models\DriverCodSettlement;
use App\Models\JournalEntry;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseDriverCodSettlementAction
{
    public function __construct(
        private readonly ReverseJournalEntryAction $reverseJournalEntryAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(DriverCodSettlement $settlement, string $reason, int $userId = 0): DriverCodSettlement
    {
        return DB::transaction(function () use ($settlement, $reason, $userId): DriverCodSettlement {
            /** @var DriverCodSettlement $lockedSettlement */
            $lockedSettlement = DriverCodSettlement::query()
                ->lockForUpdate()
                ->with(['items'])
                ->findOrFail($settlement->id);

            if ($lockedSettlement->status === DriverCodSettlementStatus::Reversed) {
                throw new DomainException('التسوية معكوسة/ملغاة مسبقاً.');
            }

            if ($lockedSettlement->status !== DriverCodSettlementStatus::Posted) {
                throw new DomainException(sprintf('لا يمكن عكس تسوية بالحالة (%s). يمكن فقط عكس التسويات المرحلة.', $lockedSettlement->status->label()));
            }

            $trimmedReason = trim($reason);
            if (empty($trimmedReason)) {
                throw new DomainException('يجب تحديد سبب عكس/إلغاء التسوية.');
            }

            // Reverse linked Journal Entry if present
            if ($lockedSettlement->journal_entry_id) {
                /** @var JournalEntry $journalEntry */
                $journalEntry = JournalEntry::query()->findOrFail($lockedSettlement->journal_entry_id);

                $this->reverseJournalEntryAction->execute(
                    $journalEntry,
                    new ReverseJournalEntryData(
                        reason: sprintf('عكس تسوية COD رقم %s: %s', $lockedSettlement->settlement_number, $trimmedReason),
                        reversalDate: now()->toDateString()
                    )
                );
            }

            $now = now();
            $effectiveUserId = $userId > 0 ? $userId : (auth()->id() ?: null);

            $lockedSettlement->update([
                'status' => DriverCodSettlementStatus::Reversed,
                'reversed_at' => $now,
                'reversed_by' => $effectiveUserId,
                'reversal_reason' => $trimmedReason,
                'updated_by' => $effectiveUserId,
            ]);

            $this->auditLogger->record('admin.cod_settlement.reversed', $lockedSettlement, metadata: [
                'settlement_number' => $lockedSettlement->settlement_number,
                'driver_name' => $lockedSettlement->driver?->name,
                'reason' => $trimmedReason,
                'total_settled' => $lockedSettlement->total_settled,
            ]);

            return $lockedSettlement->fresh(['driver', 'currency', 'treasuryAccount', 'items.delivery.order', 'journalEntry']);
        });
    }
}
