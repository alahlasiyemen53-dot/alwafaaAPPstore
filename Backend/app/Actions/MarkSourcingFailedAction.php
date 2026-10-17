<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourcingStatus;
use App\Models\OrderSourcingRequirement;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MarkSourcingFailedAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(OrderSourcingRequirement $requirement, string $failureReason, ?int $userId = null): OrderSourcingRequirement
    {
        return DB::transaction(function () use ($requirement, $failureReason, $userId): OrderSourcingRequirement {
            /** @var OrderSourcingRequirement $locked */
            $locked = OrderSourcingRequirement::query()->lockForUpdate()->findOrFail($requirement->id);

            if (! $locked->status->canBeFailed()) {
                throw new DomainException(sprintf(
                    'لا يمكن تسجيل تعذر التوريد للمهمة وهي بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $cleanReason = trim($failureReason);
            if ($cleanReason === '') {
                throw new DomainException('يجب إدخال سبب تعذر استلام البند من المورد.');
            }

            $locked->update([
                'status' => SourcingStatus::Failed,
                'failed_at' => now(),
                'failed_by' => $userId ?? auth()->id(),
                'failure_reason' => $cleanReason,
            ]);

            $this->auditLogger->record('admin.sourcing.failed', $locked, metadata: [
                'sourcing_id' => $locked->id,
                'order_id' => $locked->order_id,
                'supplier_id' => $locked->supplier_id,
                'driver_id' => $locked->assigned_driver_id,
                'failure_reason' => $cleanReason,
            ]);

            return $locked->load(['order', 'productVariant', 'supplier', 'assignedDriver']);
        });
    }
}
