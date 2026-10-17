<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrdersFromOrderAction
{
    public function __construct(
        private readonly GeneratePurchaseOrderNumberAction $generatePurchaseOrderNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function execute(Order $order, ?int $userId = null): Collection
    {
        return DB::transaction(function () use ($order, $userId): Collection {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $onDemandItems = $lockedOrder->items()
                ->with(['productVariant'])
                ->get()
                ->filter(fn (OrderItem $item): bool => $item->isOnDemand() && $item->cancelled_at === null);

            if ($onDemandItems->isEmpty()) {
                return collect();
            }

            $grouped = $onDemandItems->groupBy(function (OrderItem $item): int {
                $supplierId = (int) ($item->sourcing_supplier_id_snapshot ?? 0);
                if ($supplierId <= 0) {
                    throw new DomainException(sprintf(
                        'لا يمكن إنشاء طلب شراء للبند (%s) دون مورد معتمد.',
                        $item->sku_snapshot ?? (string) $item->id
                    ));
                }

                return $supplierId;
            });

            $created = collect();

            foreach ($grouped as $supplierId => $items) {
                $existing = PurchaseOrder::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('supplier_id', (int) $supplierId)
                    ->first();

                if ($existing !== null) {
                    $created->push($existing);
                    continue;
                }

                /** @var Supplier $supplier */
                $supplier = Supplier::query()->findOrFail((int) $supplierId);
                if (! $supplier->is_active) {
                    throw new DomainException(sprintf('المورد (%s) معطل ولا يمكن إنشاء طلب شراء له.', $supplier->name));
                }

                $purchaseOrder = PurchaseOrder::query()->create([
                    'po_number' => $this->generatePurchaseOrderNumber->execute(),
                    'order_id' => $lockedOrder->id,
                    'supplier_id' => $supplier->id,
                    'status' => PurchaseOrderStatus::Pending,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                foreach ($items as $item) {
                    /** @var OrderItem $item */
                    PurchaseOrderItem::query()->create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'order_item_id' => $item->id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name_snapshot' => $item->product_name_snapshot,
                        'variant_name_snapshot' => $item->variant_name_snapshot,
                        'sku_snapshot' => $item->sku_snapshot,
                        'supplier_item_number_snapshot' => $item->supplier_item_number_snapshot,
                        'quantity' => (float) $item->base_quantity,
                        'cost_price_snapshot' => $item->productVariant?->cost_price,
                        'status' => PurchaseOrderItemStatus::Pending,
                    ]);
                }

                $this->auditLogger->record('admin.purchase_order.created', $purchaseOrder, metadata: [
                    'po_number' => $purchaseOrder->po_number,
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'items_count' => $items->count(),
                ]);

                $created->push($purchaseOrder->load('items'));
            }

            return $created;
        });
    }
}
