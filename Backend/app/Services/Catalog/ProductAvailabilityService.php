<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTOs\VariantAvailabilityResult;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Support\ProductSupplierResolver;

final class ProductAvailabilityService
{
    /**
     * Check sellability and availability of a ProductVariant.
     *
     * @param  int|null  $warehouseId  If provided, checks stock in specific warehouse; otherwise sums across active warehouses.
     */
    public function evaluate(ProductVariant $variant, float $requestedQuantity = 1.0, ?int $warehouseId = null): VariantAvailabilityResult
    {
        $variant->loadMissing(['product.supplier', 'supplier']);

        // 1. Check Product & Variant catalog status
        if ($variant->status !== ProductVariantStatus::Active) {
            return new VariantAvailabilityResult(
                isSellable: false,
                fulfillmentMode: $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked,
                statusLabel: 'غير متوفر',
                reason: 'المتغير غير نشط.',
            );
        }

        if ($variant->product->status !== ProductStatus::Active) {
            return new VariantAvailabilityResult(
                isSellable: false,
                fulfillmentMode: $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked,
                statusLabel: 'غير متوفر',
                reason: 'المنتج غير نشط.',
            );
        }

        $mode = $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked;

        // 2. STOCKED Mode
        if ($mode === ProductVariantFulfillmentMode::Stocked) {
            $inventoryQuery = Inventory::query()->where('product_variant_id', $variant->id);

            if ($warehouseId !== null) {
                $inventoryQuery->where('warehouse_id', $warehouseId);
            }

            $inventories = $inventoryQuery->get();

            $totalAvailable = 0.0;
            foreach ($inventories as $inv) {
                $avail = max(0.0, (float) $inv->quantity - (float) $inv->reserved_quantity);
                $totalAvailable += $avail;
            }

            $isSellable = ($totalAvailable > 0.0) && ($requestedQuantity <= $totalAvailable);
            $label = $totalAvailable > 0.0 ? 'متوفر في المخزون' : 'غير متوفر';

            return new VariantAvailabilityResult(
                isSellable: $isSellable,
                fulfillmentMode: ProductVariantFulfillmentMode::Stocked,
                statusLabel: $label,
                availableStock: $totalAvailable,
                reason: $isSellable ? null : ($totalAvailable <= 0.0 ? 'الرصيد المتاح في المخزون نفد.' : 'الكمية المطلوبة تتجاوز الرصيد المتاح.'),
            );
        }

        // 3. ON_DEMAND Mode
        $supplier = ProductSupplierResolver::forVariant($variant);
        if ($supplier === null || ! $supplier->is_active) {
            return new VariantAvailabilityResult(
                isSellable: false,
                fulfillmentMode: ProductVariantFulfillmentMode::OnDemand,
                statusLabel: 'غير متوفر',
                supplier: $supplier,
                reason: $supplier === null ? 'لم يتم تحديد مورد التوفير عند الطلب.' : 'مورد التوفير عند الطلب معطل حالياً.',
            );
        }

        return new VariantAvailabilityResult(
            isSellable: true,
            fulfillmentMode: ProductVariantFulfillmentMode::OnDemand,
            statusLabel: 'متوفر عند الطلب',
            availableStock: 0.0,
            supplier: $supplier,
        );
    }
}
