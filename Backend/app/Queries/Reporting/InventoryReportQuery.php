<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\DTOs\ReportFilterData;
use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Current-snapshot inventory quantity report. Identity remains
 * Warehouse + ProductVariant. No FIFO/weighted-average/moving-average
 * valuation and no "low stock" threshold are introduced (none is approved).
 */
final class InventoryReportQuery
{
    private const ALLOWED_SORTS = ['quantity', 'reserved_quantity', 'id'];

    /**
     * @return array{
     *     summary: array{warehouses_count: int, positions_count: int, out_of_stock_count: int},
     *     warehouses: array<int, array{warehouse_id: int, warehouse_name: string, on_hand: string, reserved: string, available: string}>,
     *     rows: LengthAwarePaginator
     * }
     */
    public function execute(ReportFilterData $filters): array
    {
        $base = $this->baseQuery($filters);

        $warehouseRows = (clone $base)
            ->toBase()
            ->join('warehouses', 'warehouses.id', '=', 'inventories.warehouse_id')
            ->groupBy('inventories.warehouse_id', 'warehouses.name_ar')
            ->selectRaw('inventories.warehouse_id, warehouses.name_ar as warehouse_name, SUM(inventories.quantity) as on_hand, SUM(inventories.reserved_quantity) as reserved, SUM(inventories.quantity - inventories.reserved_quantity) as available')
            ->get();

        $warehouses = $warehouseRows->map(fn ($row): array => [
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse_name' => (string) $row->warehouse_name,
            'on_hand' => (string) $row->on_hand,
            'reserved' => (string) $row->reserved,
            'available' => (string) $row->available,
        ])->all();

        $outOfStockCount = (int) (clone $base)
            ->whereRaw('inventories.quantity - inventories.reserved_quantity <= 0')
            ->count();

        $positionsCount = (int) (clone $base)->count();
        $warehousesCount = (int) (clone $base)->distinct('warehouse_id')->count('warehouse_id');

        $sort = in_array($filters->sort, self::ALLOWED_SORTS, true) ? $filters->sort : 'id';
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        $rows = (clone $base)
            ->with(['warehouse:id,name_ar', 'productVariant:id,sku,product_id', 'productVariant.product:id,name_ar'])
            ->selectRaw('inventories.*, (inventories.quantity - inventories.reserved_quantity) as available_quantity_calc')
            ->orderBy($sort, $direction)
            ->paginate($filters->perPage)
            ->withQueryString();

        return [
            'summary' => [
                'warehouses_count' => $warehousesCount,
                'positions_count' => $positionsCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            'warehouses' => $warehouses,
            'rows' => $rows,
        ];
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    public function executeForExport(ReportFilterData $filters): \Generator
    {
        foreach ($this->baseQuery($filters)->with(['warehouse:id,name_ar', 'productVariant:id,sku,product_id', 'productVariant.product:id,name_ar'])->lazyById(500, 'inventories.id') as $inventory) {
            yield [
                $inventory->warehouse->name_ar ?? '',
                $inventory->productVariant->product->name_ar ?? '',
                $inventory->productVariant->sku ?? '',
                (string) $inventory->quantity,
                (string) $inventory->reserved_quantity,
                (string) max(0, (float) $inventory->quantity - (float) $inventory->reserved_quantity),
            ];
        }
    }

    /** @return Builder<Inventory> */
    private function baseQuery(ReportFilterData $filters): Builder
    {
        $base = Inventory::query();

        if ($filters->warehouseId !== null) {
            $base->where('warehouse_id', $filters->warehouseId);
        }

        return $base;
    }
}
