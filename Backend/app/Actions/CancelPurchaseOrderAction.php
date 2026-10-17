<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelPurchaseOrderAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason, $userId): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($locked->status === PurchaseOrderStatus::Cancelled) {
                return $locked;
            }

            if (! $locked->status->canBeCancelled()) {
                throw new DomainException(sprintf(
                    'لا يمكن إلغاء طلب الشراء وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => $reason,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->auditLogger->record('admin.purchase_order.cancelled', $locked, metadata: [
                'po_number' => $locked->po_number,
                'order_id' => $locked->order_id,
                'reason' => $reason,
            ]);

            return $locked->fresh(['supplier', 'items']);
        });
    }
}
