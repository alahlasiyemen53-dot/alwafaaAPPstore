<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssignOrderPickupDriverAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Order $order, int $driverId, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $driverId, $userId): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw new DomainException(sprintf(
                    'لا يمكن إسناد مندوب الاستلام والطلب بالحالة (%s).',
                    $lockedOrder->status->label()
                ));
            }

            /** @var Driver $driver */
            $driver = Driver::query()->findOrFail($driverId);
            if (! $driver->is_active) {
                throw new DomainException(sprintf('المندوب (%s) معطل حالياً.', $driver->name));
            }

            $lockedOrder->update([
                'pickup_driver_id' => $driver->id,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $purchaseOrders = PurchaseOrder::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->get();

            foreach ($purchaseOrders as $po) {
                if (! $po->status->canBeAssigned() && $po->status !== PurchaseOrderStatus::Assigned) {
                    continue;
                }

                $po->update([
                    'assigned_driver_id' => $driver->id,
                    'status' => PurchaseOrderStatus::Assigned,
                    'assigned_at' => now(),
                    'assigned_by' => $userId ?? auth()->id(),
                    'updated_by' => $userId ?? auth()->id(),
                    'failed_at' => null,
                    'failed_by' => null,
                    'failure_reason' => null,
                    'on_hold_at' => null,
                    'on_hold_by' => null,
                    'on_hold_reason' => null,
                ]);
            }

            $this->auditLogger->record('admin.order.pickup_driver_assigned', $lockedOrder, metadata: [
                'order_number' => $lockedOrder->order_number,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'purchase_orders_count' => $purchaseOrders->count(),
            ]);

            return $lockedOrder->load(['pickupDriver', 'purchaseOrders.supplier', 'purchaseOrders.assignedDriver']);
        });
    }
}
