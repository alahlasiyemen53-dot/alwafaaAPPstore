<?php

namespace App\Actions;

use App\DTOs\MarkDeliveryFailedData;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MarkDeliveryFailedAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Delivery $delivery, MarkDeliveryFailedData $data, int $userId): Delivery
    {
        return DB::transaction(function () use ($delivery, $data, $userId): Delivery {
            /** @var Delivery $lockedDelivery */
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            // Idempotency: if already failed
            if ($lockedDelivery->status === DeliveryStatus::Failed) {
                return $lockedDelivery->load(['order', 'driver']);
            }

            if (! $lockedDelivery->canBeMarkedFailed()) {
                throw new DomainException(sprintf(
                    'لا يمكن تسجيل تعذر التسليم للشحنة لأنها بالحالة (%s).',
                    $lockedDelivery->status->label()
                ));
            }

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedDelivery->order_id);

            $now = now();

            // 1. Update Delivery (Goods remain physically with driver, warehouse stock does NOT increase)
            $lockedDelivery->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => $now,
                'failed_by' => $userId > 0 ? $userId : null,
                'failure_reason_code' => $data->failureReasonCode,
                'failure_notes' => $data->failureNotes,
            ]);

            // 2. Update Order Status
            $lockedOrder->update([
                'status' => OrderStatus::DeliveryFailed,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            // 3. Audit
            $this->auditLogger->record('admin.delivery.failed', $lockedDelivery, metadata: [
                'delivery_number' => $lockedDelivery->delivery_number,
                'order_number' => $lockedOrder->order_number,
                'failure_reason_code' => $data->failureReasonCode,
                'failure_notes' => $data->failureNotes,
            ]);

            return $lockedDelivery->load(['order.customer', 'driver']);
        });
    }
}
