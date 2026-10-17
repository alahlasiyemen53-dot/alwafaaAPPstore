<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class HoldPurchaseOrderAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason, $userId): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($locked->status === PurchaseOrderStatus::OnHold) {
                $locked->update([
                    'on_hold_reason' => $reason,
                    'updated_by' => $userId ?? auth()->id(),
                ]);

                return $locked;
            }

            if (! $locked->status->canBeHeld()) {
                throw new DomainException(sprintf(
                    'لا يمكن تعليق طلب الشراء وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            $locked->update([
                'status' => PurchaseOrderStatus::OnHold,
                'on_hold_at' => now(),
                'on_hold_by' => $userId ?? auth()->id(),
                'on_hold_reason' => $reason,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->auditLogger->record('admin.purchase_order.on_hold', $locked, metadata: [
                'po_number' => $locked->po_number,
                'order_id' => $locked->order_id,
                'reason' => $reason,
            ]);

            return $locked->fresh(['supplier', 'items']);
        });
    }
}
