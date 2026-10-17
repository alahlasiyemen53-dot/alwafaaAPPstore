<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SourcingStatus;
use App\Models\Order;
use App\Models\OrderSourcingRequirement;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MarkPurchaseOrderCollectedAction
{
    public function __construct(
        private readonly PromoteOrderReadyForDeliveryAction $promoteOrderReadyForDelivery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $userId): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);

            if ($locked->status === PurchaseOrderStatus::Collected) {
                return $locked;
            }

            if (! $locked->status->canBeCollected()) {
                throw new DomainException(sprintf(
                    'لا يمكن تأكيد استلام طلب الشراء وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            if ($order->pickup_driver_id === null) {
                throw new DomainException(
                    'لا يمكن استلام الطلب من المورد قبل إسناد مندوب التوصيل للطلب.'
                );
            }

            $activeItems = $locked->items->filter(fn ($item) => $item->isActive());
            if ($activeItems->isEmpty()) {
                throw new DomainException('لا يمكن تأكيد استلام طلب شراء لا يحتوي على بنود نشطة.');
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Collected,
                'collected_at' => now(),
                'collected_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
                'on_hold_at' => null,
                'on_hold_by' => null,
                'on_hold_reason' => null,
                'failed_at' => null,
                'failed_by' => null,
                'failure_reason' => null,
            ]);

            $orderItemIds = $activeItems->pluck('order_item_id')->all();
            OrderSourcingRequirement::query()
                ->whereIn('order_item_id', $orderItemIds)
                ->whereIn('status', [SourcingStatus::Pending, SourcingStatus::Assigned])
                ->update([
                    'status' => SourcingStatus::Collected,
                    'collected_at' => now(),
                    'collected_by' => $userId ?? auth()->id(),
                ]);

            $this->auditLogger->record('admin.purchase_order.collected', $locked, metadata: [
                'po_number' => $locked->po_number,
                'order_id' => $locked->order_id,
                'supplier_id' => $locked->supplier_id,
                'driver_id' => $locked->assigned_driver_id ?? $order->pickup_driver_id,
            ]);

            $this->promoteOrderReadyForDelivery->execute($order, $userId);

            return $locked->fresh(['items', 'supplier', 'assignedDriver', 'order']);
        });
    }
}
