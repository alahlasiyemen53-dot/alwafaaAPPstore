<?php

namespace App\Actions;

use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\StockMovement;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RecordStockMovementAction
{
    public function execute(
        int $warehouseId,
        int $productVariantId,
        StockMovementType $movementType,
        StockMovementDirection $direction,
        float $quantity,
        Model $reference,
        ?string $reasonCode = null,
        ?string $notes = null,
        ?DateTimeInterface $occurredAt = null,
        ?int $createdBy = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new DomainException('يجب أن تكون كمية الحركة المخزنية أكبر من الصفر.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productVariantId,
            $movementType,
            $direction,
            $quantity,
            $reference,
            $reasonCode,
            $notes,
            $occurredAt,
            $createdBy,
        ): StockMovement {
            $inventory = Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                try {
                    $inventory = Inventory::query()->create([
                        'warehouse_id' => $warehouseId,
                        'product_variant_id' => $productVariantId,
                        'quantity' => 0.0000,
                        'reserved_quantity' => 0.0000,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $inventory = Inventory::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_variant_id', $productVariantId)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $inventory = Inventory::query()
                    ->where('id', $inventory->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $currentQuantity = (float) $inventory->quantity;

            if ($direction === StockMovementDirection::Out) {
                if ($currentQuantity < $quantity) {
                    throw new DomainException(sprintf(
                        'الرصيد المتوفر في المستودع (%s) غير كافٍ لإتمام حركة الصرف (%s).',
                        $currentQuantity,
                        $quantity
                    ));
                }
                $inventory->quantity = $currentQuantity - $quantity;
            } else {
                $inventory->quantity = $currentQuantity + $quantity;
            }

            $inventory->save();

            return StockMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $productVariantId,
                'movement_type' => $movementType,
                'direction' => $direction,
                'quantity' => $quantity,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'occurred_at' => $occurredAt ?? now(),
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        });
    }
}
