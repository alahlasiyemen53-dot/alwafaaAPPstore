<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AssignOrderToDriverData;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelPurchaseOrderItemAction
{
    public function __construct(
        private readonly PromoteOrderReadyForDeliveryAction $promoteOrderReadyForDelivery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrderItem $purchaseOrderItem, string $reason, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderItem, $reason, $userId): PurchaseOrder {
            /** @var PurchaseOrderItem $lockedItem */
            $lockedItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($purchaseOrderItem->id);

            if ($lockedItem->status === PurchaseOrderItemStatus::Cancelled) {
                return PurchaseOrder::query()->with('items')->findOrFail($lockedItem->purchase_order_id);
            }

            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($lockedItem->purchase_order_id);

            if ($po->status === PurchaseOrderStatus::Collected) {
                throw new DomainException('لا يمكن إلغاء بند تم استلامه بالفعل من المورد.');
            }

            $lockedItem->update([
                'status' => PurchaseOrderItemStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => $reason,
            ]);

            /** @var OrderItem $orderItem */
            $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($lockedItem->order_item_id);
            $lineTotal = (float) $orderItem->line_total;
            $lineDiscount = (float) $orderItem->discount_amount;
            $lineSubtotal = (float) $orderItem->line_subtotal;
            $baseLineTotal = (float) $orderItem->base_currency_line_total;

            $orderItem->update([
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => $reason,
                'quantity' => 0,
                'base_quantity' => 0,
                'line_subtotal' => 0,
                'discount_amount' => 0,
                'item_discount_amount' => 0,
                'allocated_order_discount' => 0,
                'line_total' => 0,
                'base_currency_line_total' => 0,
            ]);

            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($po->order_id);
            $order->update([
                'subtotal' => max(0, round((float) $order->subtotal - $lineSubtotal, 4)),
                'discount_amount' => max(0, round((float) $order->discount_amount - $lineDiscount, 4)),
                'total' => max(0, round((float) $order->total - $lineTotal, 4)),
                'base_currency_total' => max(0, round((float) $order->base_currency_total - $baseLineTotal, 4)),
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $remainingActive = PurchaseOrderItem::query()
                ->where('purchase_order_id', $po->id)
                ->where('status', PurchaseOrderItemStatus::Pending)
                ->count();

            if ($remainingActive === 0) {
                $po->update([
                    'status' => PurchaseOrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => $userId ?? auth()->id(),
                    'cancellation_reason' => 'أُلغيت جميع بنود طلب الشراء',
                    'updated_by' => $userId ?? auth()->id(),
                ]);
            }

            $this->auditLogger->record('admin.purchase_order.item_cancelled', $po, metadata: [
                'po_number' => $po->po_number,
                'purchase_order_item_id' => $lockedItem->id,
                'order_item_id' => $orderItem->id,
                'reason' => $reason,
            ]);

            $this->promoteOrderReadyForDelivery->execute(
                $order->fresh(['purchaseOrders']),
                $userId,
                'جاهز للتوصيل بعد إلغاء البنود الناقصة'
            );

            return $po->fresh(['items', 'supplier', 'order']);
        });
    }
}
