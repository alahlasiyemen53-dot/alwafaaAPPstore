<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ChangePurchaseOrderSupplierAction
{
    public function __construct(
        private readonly GeneratePurchaseOrderNumberAction $generatePurchaseOrderNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Change supplier for a whole PO, or move a single item to another supplier's PO.
     */
    public function execute(
        PurchaseOrder $purchaseOrder,
        int $newSupplierId,
        ?int $purchaseOrderItemId = null,
        ?int $userId = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $newSupplierId, $purchaseOrderItemId, $userId): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);

            if (! $locked->status->canChangeSupplier()) {
                throw new DomainException(sprintf(
                    'لا يمكن تغيير مورد طلب الشراء وهو بالحالة (%s).',
                    $locked->status->label()
                ));
            }

            if ((int) $locked->supplier_id === $newSupplierId) {
                throw new DomainException('المورد الجديد مطابق للمورد الحالي.');
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail($newSupplierId);
            if (! $supplier->is_active) {
                throw new DomainException(sprintf('المورد (%s) معطل حالياً.', $supplier->name));
            }

            if ($purchaseOrderItemId !== null) {
                return $this->moveSingleItem($locked, $supplier, $purchaseOrderItemId, $userId);
            }

            $conflict = PurchaseOrder::query()
                ->where('order_id', $locked->order_id)
                ->where('supplier_id', $supplier->id)
                ->where('id', '!=', $locked->id)
                ->whereNotIn('status', [PurchaseOrderStatus::Cancelled->value, PurchaseOrderStatus::Failed->value])
                ->lockForUpdate()
                ->first();

            if ($conflict !== null) {
            foreach ($locked->items->filter(fn ($i) => $i->isActive()) as $item) {
                $item->update(['purchase_order_id' => $conflict->id]);
                \App\Models\OrderItem::query()->whereKey($item->order_item_id)->update([
                    'sourcing_supplier_id_snapshot' => $supplier->id,
                    'supplier_name_snapshot' => $supplier->name,
                ]);
            }

                $locked->update([
                    'status' => PurchaseOrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => $userId ?? auth()->id(),
                    'cancellation_reason' => 'دُمجت البنود مع طلب شراء المورد البديل '.$conflict->po_number,
                    'updated_by' => $userId ?? auth()->id(),
                ]);

                if ($conflict->status === PurchaseOrderStatus::Collected) {
                    $conflict->update([
                        'status' => PurchaseOrderStatus::Assigned,
                        'collected_at' => null,
                        'collected_by' => null,
                        'updated_by' => $userId ?? auth()->id(),
                    ]);
                }

                $this->auditLogger->record('admin.purchase_order.supplier_changed_merged', $conflict, metadata: [
                    'from_po' => $locked->po_number,
                    'to_po' => $conflict->po_number,
                    'new_supplier_id' => $supplier->id,
                ]);

                return $conflict->fresh(['items', 'supplier']);
            }

            $oldSupplierId = (int) $locked->supplier_id;
            $locked->update([
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Pending,
                'assigned_driver_id' => null,
                'assigned_at' => null,
                'assigned_by' => null,
                'collected_at' => null,
                'collected_by' => null,
                'on_hold_at' => null,
                'on_hold_by' => null,
                'on_hold_reason' => null,
                'failed_at' => null,
                'failed_by' => null,
                'failure_reason' => null,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            foreach ($locked->items->filter(fn ($i) => $i->isActive()) as $item) {
                \App\Models\OrderItem::query()->whereKey($item->order_item_id)->update([
                    'sourcing_supplier_id_snapshot' => $supplier->id,
                    'supplier_name_snapshot' => $supplier->name,
                ]);
            }

            $this->auditLogger->record('admin.purchase_order.supplier_changed', $locked, metadata: [
                'po_number' => $locked->po_number,
                'old_supplier_id' => $oldSupplierId,
                'new_supplier_id' => $supplier->id,
            ]);

            return $locked->fresh(['items', 'supplier']);
        });
    }

    private function moveSingleItem(
        PurchaseOrder $locked,
        Supplier $supplier,
        int $purchaseOrderItemId,
        ?int $userId,
    ): PurchaseOrder {
        /** @var PurchaseOrderItem|null $item */
        $item = $locked->items->firstWhere('id', $purchaseOrderItemId);
        if ($item === null || ! $item->isActive()) {
            throw new DomainException('بند طلب الشراء غير موجود أو ملغى.');
        }

        $target = PurchaseOrder::query()
            ->where('order_id', $locked->order_id)
            ->where('supplier_id', $supplier->id)
            ->whereNotIn('status', [PurchaseOrderStatus::Cancelled->value])
            ->lockForUpdate()
            ->first();

        if ($target === null) {
            $target = PurchaseOrder::query()->create([
                'po_number' => $this->generatePurchaseOrderNumber->execute(),
                'order_id' => $locked->order_id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Pending,
                'created_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
            ]);
        } elseif ($target->status === PurchaseOrderStatus::Collected) {
            $target->update([
                'status' => PurchaseOrderStatus::Assigned,
                'collected_at' => null,
                'collected_by' => null,
                'updated_by' => $userId ?? auth()->id(),
            ]);
        }

        $item->update(['purchase_order_id' => $target->id]);
        \App\Models\OrderItem::query()->whereKey($item->order_item_id)->update([
            'sourcing_supplier_id_snapshot' => $supplier->id,
            'supplier_name_snapshot' => $supplier->name,
        ]);

        $remainingActive = PurchaseOrderItem::query()
            ->where('purchase_order_id', $locked->id)
            ->where('status', PurchaseOrderItemStatus::Pending)
            ->count();

        if ($remainingActive === 0) {
            $locked->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'cancellation_reason' => 'نُقلت جميع البنود لمورد بديل',
                'updated_by' => $userId ?? auth()->id(),
            ]);
        }

        $this->auditLogger->record('admin.purchase_order.item_supplier_changed', $target, metadata: [
            'from_po' => $locked->po_number,
            'to_po' => $target->po_number,
            'purchase_order_item_id' => $item->id,
            'new_supplier_id' => $supplier->id,
        ]);

        return $target->fresh(['items', 'supplier']);
    }
}
