<?php

namespace App\Actions;

use App\DTOs\CreateAccountingPeriodData;
use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateAccountingPeriodAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateAccountingPeriodData $data): AccountingPeriod
    {
        return DB::transaction(function () use ($data): AccountingPeriod {
            if ($data->startsAt > $data->endsAt) {
                throw new UnprocessableEntityHttpException('تاريخ بداية الفترة المحاسبية يجب أن يسبق أو يساوي تاريخ نهايتها.');
            }

            $userId = auth()->id() > 0 ? auth()->id() : null;

            $period = AccountingPeriod::create([
                'name' => $data->name,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'status' => AccountingPeriodStatus::Open,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.accounting_period.created', $period, metadata: [
                'name' => $period->name,
                'starts_at' => $period->starts_at->toDateString(),
                'ends_at' => $period->ends_at->toDateString(),
            ]);

            return $period;
        });
    }
}
