<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourcingStatus;
use App\Models\OrderSourcingRequirement;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmSourcingCollectedAction
{
    public function __construct(
        private readonly MarkPurchaseOrderCollectedAction $markPurchaseOrderCollected,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(OrderSourcingRequirement $requirement, ?int $userId = null): OrderSourcingRequirement
    {
        return DB::transaction(function () use ($requirement, $userId): OrderSourcingRequirement {
            /** @var OrderSourcingRequirement $locked */
            $locked = OrderSourcingRequirement::query()->lockForUpdate()->findOrFail($requirement->id);

            // Idempotency
            if ($locked->status === SourcingStatus::Collected) {
                return $locked;
            }

            if (! in_array($locked->status, [SourcingStatus::Assigned, SourcingStatus::Pending], true)) {
                throw new DomainException(sprintf(
                    'لا يمكن تأكيد استلام البند من المورد وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $order = $locked->order()->lockForUpdate()->first();
            if ($order === null || $order->pickup_driver_id === null) {
                throw new DomainException(
                    'لا يمكن استلام الطلب من المورد قبل إسناد مندوب التوصيل للطلب.'
                );
            }

            $locked->update([
                'status' => SourcingStatus::Collected,
                'collected_at' => now(),
                'collected_by' => $userId ?? auth()->id(),
            ]);

            $this->auditLogger->record('admin.sourcing.collected', $locked, metadata: [
                'sourcing_id' => $locked->id,
                'order_id' => $locked->order_id,
                'supplier_id' => $locked->supplier_id,
                'driver_id' => $locked->assigned_driver_id,
                'quantity' => (float) $locked->quantity,
                'collected_at' => $locked->collected_at?->toDateTimeString(),
            ]);

            $this->syncPurchaseOrderIfReady((int) $locked->order_id, (int) $locked->supplier_id, $userId);

            return $locked->load(['order', 'productVariant', 'supplier', 'assignedDriver']);
        });
    }

    private function syncPurchaseOrderIfReady(int $orderId, int $supplierId, ?int $userId): void
    {
        $uncollected = OrderSourcingRequirement::query()
            ->where('order_id', $orderId)
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', SourcingStatus::Collected)
            ->whereNotIn('status', [SourcingStatus::Cancelled])
            ->exists();

        if ($uncollected) {
            return;
        }

        $purchaseOrder = PurchaseOrder::query()
            ->where('order_id', $orderId)
            ->where('supplier_id', $supplierId)
            ->whereNotIn('status', ['cancelled', 'collected'])
            ->first();

        if ($purchaseOrder !== null) {
            $this->markPurchaseOrderCollected->execute($purchaseOrder, $userId);
        }
    }
}
