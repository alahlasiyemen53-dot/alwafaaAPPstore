<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Enums\StockMovementDirection;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\StockMovement;
use Illuminate\Console\Command;

final class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile {--warehouse= : معرف المستودع الاختياري للمطابقة}';

    protected $description = 'مطابقة أرصدة المخزون مع إجمالي حركات المخزون المسجلة وكشف أي فروقات';

    public function handle(): int
    {
        $warehouseId = $this->option('warehouse');

        $this->info('بدء عملية مطابقة المخزون مع سجل الحركات...');

        $query = Inventory::query()->with(['warehouse', 'productVariant.product']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $inventories = $query->get();
        $discrepancies = [];
        $reservationDiscrepancies = [];

        foreach ($inventories as $inventory) {
            $movementsIn = (float) StockMovement::query()
                ->where('warehouse_id', $inventory->warehouse_id)
                ->where('product_variant_id', $inventory->product_variant_id)
                ->where('direction', StockMovementDirection::In)
                ->sum('quantity');

            $movementsOut = (float) StockMovement::query()
                ->where('warehouse_id', $inventory->warehouse_id)
                ->where('product_variant_id', $inventory->product_variant_id)
                ->where('direction', StockMovementDirection::Out)
                ->sum('quantity');

            $expectedQuantity = round($movementsIn - $movementsOut, 4);
            $actualQuantity = round((float) $inventory->quantity, 4);

            if (abs($expectedQuantity - $actualQuantity) > 0.0001) {
                $discrepancies[] = [
                    'warehouse' => $inventory->warehouse->name_ar ?? $inventory->warehouse_id,
                    'sku' => $inventory->productVariant->sku ?? $inventory->product_variant_id,
                    'actual' => $actualQuantity,
                    'expected' => $expectedQuantity,
                    'difference' => round($actualQuantity - $expectedQuantity, 4),
                ];
            }

            // Reconcile reservations: reserved_quantity == SUM(ACTIVE inventory_reservations.quantity)
            $expectedReserved = (float) InventoryReservation::query()
                ->where('warehouse_id', $inventory->warehouse_id)
                ->where('product_variant_id', $inventory->product_variant_id)
                ->where('status', ReservationStatus::Active)
                ->sum('quantity');

            $actualReserved = round((float) $inventory->reserved_quantity, 4);
            $expectedReserved = round($expectedReserved, 4);

            if (abs($expectedReserved - $actualReserved) > 0.0001 || $actualReserved > $actualQuantity) {
                $reservationDiscrepancies[] = [
                    'warehouse' => $inventory->warehouse->name_ar ?? $inventory->warehouse_id,
                    'sku' => $inventory->productVariant->sku ?? $inventory->product_variant_id,
                    'actual_reserved' => $actualReserved,
                    'expected_reserved' => $expectedReserved,
                    'quantity_on_hand' => $actualQuantity,
                    'difference' => round($actualReserved - $expectedReserved, 4),
                    'oversold' => $actualReserved > $actualQuantity ? 'نعم (بيع زائد)' : 'لا',
                ];
            }
        }

        $hasErrors = false;

        if (! empty($discrepancies)) {
            $this->error(sprintf('تم العثور على (%d) فروقات بين أرصدة المخزون وسجل الحركات:', count($discrepancies)));
            $this->table(['المستودع', 'SKU', 'الرصيد الفعلي', 'المتوقع من الحركات', 'الفارق'], $discrepancies);
            $hasErrors = true;
        }

        if (! empty($reservationDiscrepancies)) {
            $this->error(sprintf('تم العثور على (%d) فروقات في أرصدة حجز المخزون:', count($reservationDiscrepancies)));
            $this->table(['المستودع', 'SKU', 'المحجوز الفعلي', 'المتوقع من الحجوزات النشطة', 'الرصيد الكلي', 'الفارق', 'تجاوز الرصيد'], $reservationDiscrepancies);
            $hasErrors = true;
        }

        if (! $hasErrors) {
            $this->info(sprintf('اكتملت المطابقة بنجاح! تم فحص (%d) سجلاً للمخزون، وجميع الأرصدة والحجوزات متطابقة تماماً بنسبة 100%%.', $inventories->count()));

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
