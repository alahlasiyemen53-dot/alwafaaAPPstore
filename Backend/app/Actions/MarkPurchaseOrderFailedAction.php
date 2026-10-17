<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MarkPurchaseOrderFailedAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, string $failureReason, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $failureReason, $userId): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($locked->status === PurchaseOrderStatus::Failed) {
                return $locked;
            }

            if (! $locked->status->canBeFailed()) {
                throw new DomainException(sprintf(
                    'لا يمكن تسجيل تعذر استلام طلب الشراء وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Failed,
                'failed_at' => now(),
                'failed_by' => $userId ?? auth()->id(),
                'failure_reason' => $failureReason,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->auditLogger->record('admin.purchase_order.failed', $locked, metadata: [
                'po_number' => $locked->po_number,
                'order_id' => $locked->order_id,
                'failure_reason' => $failureReason,
            ]);

            return $locked->fresh(['supplier', 'items']);
        });
    }
}
