<?php

namespace App\Actions;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CloseAccountingPeriodAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        return DB::transaction(function () use ($period): AccountingPeriod {
            /** @var AccountingPeriod $lockedPeriod */
            $lockedPeriod = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($lockedPeriod->status === AccountingPeriodStatus::Closed) {
                throw new ConflictHttpException('الفترة المحاسبية مغلقة بالفعل.');
            }

            $userId = auth()->id() > 0 ? auth()->id() : null;

            $lockedPeriod->update([
                'status' => AccountingPeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.accounting_period.closed', $lockedPeriod, metadata: [
                'name' => $lockedPeriod->name,
                'closed_at' => $lockedPeriod->closed_at->toDateTimeString(),
            ]);

            return $lockedPeriod;
        });
    }
}
