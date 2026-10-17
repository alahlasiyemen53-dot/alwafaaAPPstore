<?php

namespace App\Actions;

use App\DTOs\SalesReturnItemData;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostSalesReturnAction
{
    public function __construct(
        private readonly CalculateSalesReturnTotalsAction $calculateTotals,
        private readonly ClawbackLoyaltyPointsAction $clawbackLoyaltyPointsAction,
    ) {}

    public function execute(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $userId) {
            // 1. Lock SalesReturn record
            $lockedReturn = SalesReturn::query()
                ->where('id', $salesReturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->status === SalesReturnStatus::Posted) {
                // Idempotent: already posted
                return $lockedReturn->load(['items', 'order', 'warehouse', 'currency', 'stockMovements']);
            }

            if (! $lockedReturn->canBePosted()) {
                throw new DomainException('لا يمكن اعتماد المرتجع لأنه ليس في حالة مسودة.');
            }

            // 2. Lock items
            $items = $lockedReturn->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw new DomainException('لا يمكن اعتماد مرتجع لا يحتوي على أي بنود.');
            }

            // 3. Re-verify returnable quantities authoritatively under transaction lock
            $itemsData = $items->map(function ($it) {
                return new SalesReturnItemData(
                    orderItemId: $it->order_item_id,
                    productVariantId: $it->product_variant_id,
                    returnQuantity: (float) $it->return_quantity,
                    reasonCode: $it->reason_code?->value,
                    notes: $it->notes,
                );
            })->all();

            // Calculate totals and re-validate returnable quantity against physical SALE OUT
            $this->calculateTotals->execute($lockedReturn->order_id, $itemsData, $lockedReturn->id);

            // 4. Lock inventory records in deterministic order (product_variant_id ASC)
            $variantIds = $items->pluck('product_variant_id')->unique()->sort()->values()->all();

            $inventories = [];
            foreach ($variantIds as $variantId) {
                $inv = Inventory::query()
                    ->where('warehouse_id', $lockedReturn->warehouse_id)
                    ->where('product_variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    $inv = Inventory::create([
                        'warehouse_id' => $lockedReturn->warehouse_id,
                        'product_variant_id' => $variantId,
                        'quantity' => 0.0,
                        'reserved_quantity' => 0.0,
                    ]);
                }

                $inventories[$variantId] = $inv;
            }

            // 5. Create StockMovements (SALE_RETURN, IN) and increase Inventory quantity
            $now = now();
            foreach ($items as $item) {
                StockMovement::create([
                    'warehouse_id' => $lockedReturn->warehouse_id,
                    'product_variant_id' => $item->product_variant_id,
                    'movement_type' => StockMovementType::SaleReturn,
                    'direction' => StockMovementDirection::In,
                    'quantity' => $item->return_base_quantity,
                    'reference_type' => 'SalesReturn',
                    'reference_id' => $lockedReturn->id,
                    'reason_code' => $item->getAttribute('reason_code') !== null ? $item->reason_code->value : $lockedReturn->reason_code->value,
                    'notes' => sprintf('مرتجع مبيعات رقم %s للطلب %s', $lockedReturn->return_number, $lockedReturn->order->order_number),
                    'occurred_at' => $now,
                    'created_by' => $userId,
                ]);

                // Increase inventory quantity
                $inv = $inventories[$item->product_variant_id];
                $inv->quantity = round((float) $inv->quantity + (float) $item->return_base_quantity, 4);
                $inv->save();
            }

            // 6. Mark SalesReturn as POSTED
            $lockedReturn->update([
                'status' => SalesReturnStatus::Posted,
                'posted_at' => $now,
                'posted_by' => $userId,
                'updated_by' => $userId,
            ]);

            // 7. Clawback loyalty points if order previously earned points
            $this->clawbackLoyaltyPointsAction->execute($lockedReturn, $userId);

            return $lockedReturn->fresh(['items', 'order', 'warehouse', 'currency', 'stockMovements']);
        });
    }
}
