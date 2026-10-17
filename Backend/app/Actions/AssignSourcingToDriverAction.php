<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourcingStatus;
use App\Models\Driver;
use App\Models\OrderSourcingRequirement;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssignSourcingToDriverAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(OrderSourcingRequirement $requirement, int $driverId, ?int $userId = null): OrderSourcingRequirement
    {
        return DB::transaction(function () use ($requirement, $driverId, $userId): OrderSourcingRequirement {
            /** @var OrderSourcingRequirement $locked */
            $locked = OrderSourcingRequirement::query()->lockForUpdate()->findOrFail($requirement->id);

            if (! $locked->status->canBeAssigned()) {
                throw new DomainException(sprintf(
                    'لا يمكن إسناد مهمة التوريد وهي بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            /** @var Driver $driver */
            $driver = Driver::query()->findOrFail($driverId);
            if (! $driver->is_active) {
                throw new DomainException(sprintf('المندوب (%s) معطل حالياً ولا يمكن إسناد مهمة التوريد إليه.', $driver->name));
            }

            $locked->update([
                'assigned_driver_id' => $driver->id,
                'status' => SourcingStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $userId ?? auth()->id(),
                'failed_at' => null,
                'failed_by' => null,
                'failure_reason' => null,
            ]);

            $this->auditLogger->record('admin.sourcing.assigned', $locked, metadata: [
                'sourcing_id' => $locked->id,
                'order_id' => $locked->order_id,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'supplier_id' => $locked->supplier_id,
                'quantity' => (float) $locked->quantity,
            ]);

            return $locked->load(['order', 'productVariant', 'supplier', 'assignedDriver']);
        });
    }
}
