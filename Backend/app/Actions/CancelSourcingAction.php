<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourcingStatus;
use App\Models\OrderSourcingRequirement;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelSourcingAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(OrderSourcingRequirement $requirement, ?string $reason = null, ?int $userId = null): OrderSourcingRequirement
    {
        return DB::transaction(function () use ($requirement, $reason, $userId): OrderSourcingRequirement {
            /** @var OrderSourcingRequirement $locked */
            $locked = OrderSourcingRequirement::query()->lockForUpdate()->findOrFail($requirement->id);

            if ($locked->status === SourcingStatus::Cancelled) {
                return $locked;
            }

            if ($locked->status === SourcingStatus::Collected) {
                throw new DomainException('لا يمكن إلغاء مهمة التوريد بعد تأكيد استلام البضاعة من المورد؛ البضاعة بحوزة المندوب وتحتاج لإجراء استرجاع استثنائي للمورد.');
            }

            if (! $locked->status->canBeCancelled()) {
                throw new DomainException(sprintf(
                    'لا يمكن إلغاء مهمة التوريد وهي بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $locked->update([
                'status' => SourcingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            $this->auditLogger->record('admin.sourcing.cancelled', $locked, metadata: [
                'sourcing_id' => $locked->id,
                'order_id' => $locked->order_id,
                'driver_id' => $locked->assigned_driver_id,
                'cancellation_reason' => $reason,
            ]);

            return $locked->load(['order', 'productVariant', 'supplier', 'assignedDriver']);
        });
    }
}
