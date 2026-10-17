<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreatePurchaseInvoiceFromDriverDeliveryAction
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
        private readonly PostPurchaseInvoiceAction $postPurchaseInvoiceAction,
    ) {}

    /**
     * Create (and post) purchase invoices for collected purchase orders when the driver marks delivery.
     * Idempotent per purchase_order_id (fallback: order + supplier).
     *
     * @return list<PurchaseInvoice>
     */
    public function execute(Order $order, int $userId = 0): array
    {
        return DB::transaction(function () use ($order, $userId): array {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with(['items.productVariant', 'currency', 'purchaseOrders.items'])
                ->findOrFail($order->id);

            $purchaseOrders = $lockedOrder->purchaseOrders
                ->filter(fn (PurchaseOrder $po): bool => $po->status === PurchaseOrderStatus::Collected);

            if ($purchaseOrders->isNotEmpty()) {
                return $this->createFromPurchaseOrders($lockedOrder, $purchaseOrders, $userId);
            }

            return $this->createFromOnDemandItemsLegacy($lockedOrder, $userId);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurchaseOrder>  $purchaseOrders
     * @return list<PurchaseInvoice>
     */
    private function createFromPurchaseOrders(Order $lockedOrder, $purchaseOrders, int $userId): array
    {
        $created = [];
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $currencyId = (int) ($lockedOrder->currency_id ?: $baseCurrency->id);
        $warehouseId = (int) $lockedOrder->warehouse_id;

        foreach ($purchaseOrders as $purchaseOrder) {
            $exists = PurchaseInvoice::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $existsLegacy = PurchaseInvoice::query()
                ->where('source_order_id', $lockedOrder->id)
                ->where('supplier_id', (int) $purchaseOrder->supplier_id)
                ->exists();

            if ($existsLegacy) {
                continue;
            }

            $subtotal = 0.0;
            $lineRows = [];
            foreach ($purchaseOrder->items->filter(fn ($i) => $i->isActive()) as $poItem) {
                /** @var OrderItem|null $orderItem */
                $orderItem = $lockedOrder->items->firstWhere('id', $poItem->order_item_id);
                if ($orderItem === null || $orderItem->isCancelled()) {
                    continue;
                }

                $unitId = (int) ($orderItem->unit_id ?? 0);
                if ($unitId <= 0) {
                    continue;
                }

                $cost = (float) ($poItem->cost_price_snapshot
                    ?? $orderItem->productVariant?->cost_price
                    ?? 0);
                $qty = (float) $orderItem->quantity;
                if ($qty <= 0) {
                    continue;
                }

                $lineTotal = round($qty * $cost, 4);
                $subtotal += $lineTotal;
                $lineRows[] = [
                    'product_variant_id' => (int) $poItem->product_variant_id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'base_quantity' => (float) ($orderItem->base_quantity ?: $qty),
                    'free_quantity' => 0,
                    'unit_cost' => $cost,
                    'line_subtotal' => $lineTotal,
                    'discount_amount' => 0,
                    'line_total' => $lineTotal,
                ];
            }

            if ($lineRows === []) {
                continue;
            }

            $invoice = $this->storeInvoice(
                lockedOrder: $lockedOrder,
                supplierId: (int) $purchaseOrder->supplier_id,
                warehouseId: $warehouseId,
                currencyId: $currencyId,
                subtotal: $subtotal,
                lineRows: $lineRows,
                purchaseOrderId: (int) $purchaseOrder->id,
                userId: $userId,
            );

            $created[] = $invoice;
        }

        return $created;
    }

    /**
     * @return list<PurchaseInvoice>
     */
    private function createFromOnDemandItemsLegacy(Order $lockedOrder, int $userId): array
    {
        $onDemandItems = $lockedOrder->items->filter(
            fn (OrderItem $item): bool => ! $item->isCancelled()
                && ($item->fulfillment_mode_snapshot ?? ProductVariantFulfillmentMode::Stocked)
                    === ProductVariantFulfillmentMode::OnDemand
        );

        if ($onDemandItems->isEmpty()) {
            return [];
        }

        $grouped = $onDemandItems->groupBy(function (OrderItem $item): int {
            return (int) ($item->sourcing_supplier_id_snapshot
                ?? $item->productVariant?->supplier_id
                ?? 0);
        })->filter(fn ($_, $supplierId) => (int) $supplierId > 0);

        $created = [];
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $currencyId = (int) ($lockedOrder->currency_id ?: $baseCurrency->id);
        $warehouseId = (int) $lockedOrder->warehouse_id;

        foreach ($grouped as $supplierId => $items) {
            $exists = PurchaseInvoice::query()
                ->where('source_order_id', $lockedOrder->id)
                ->where('supplier_id', (int) $supplierId)
                ->exists();

            if ($exists) {
                continue;
            }

            $subtotal = 0.0;
            $lineRows = [];
            foreach ($items as $item) {
                /** @var OrderItem $item */
                $unitId = (int) ($item->unit_id ?? 0);
                if ($unitId <= 0) {
                    Log::warning('Skipping on-demand purchase invoice line without unit', [
                        'order_id' => $lockedOrder->id,
                        'order_item_id' => $item->id,
                    ]);
                    continue;
                }

                $cost = (float) ($item->productVariant?->cost_price ?? 0);
                $qty = (float) $item->quantity;
                $lineTotal = round($qty * $cost, 4);
                $subtotal += $lineTotal;
                $lineRows[] = [
                    'product_variant_id' => (int) $item->product_variant_id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'base_quantity' => (float) ($item->base_quantity ?: $qty),
                    'free_quantity' => 0,
                    'unit_cost' => $cost,
                    'line_subtotal' => $lineTotal,
                    'discount_amount' => 0,
                    'line_total' => $lineTotal,
                ];
            }

            if ($lineRows === []) {
                continue;
            }

            $created[] = $this->storeInvoice(
                lockedOrder: $lockedOrder,
                supplierId: (int) $supplierId,
                warehouseId: $warehouseId,
                currencyId: $currencyId,
                subtotal: $subtotal,
                lineRows: $lineRows,
                purchaseOrderId: null,
                userId: $userId,
            );
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $lineRows
     */
    private function storeInvoice(
        Order $lockedOrder,
        int $supplierId,
        int $warehouseId,
        int $currencyId,
        float $subtotal,
        array $lineRows,
        ?int $purchaseOrderId,
        int $userId,
    ): PurchaseInvoice {
        $year = date('Y');
        $count = PurchaseInvoice::query()->whereYear('invoice_date', $year)->count();
        $invoiceNumber = sprintf('PI-%s-%05d', $year, $count + 1);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'currency_id' => $currencyId,
            'supplier_invoice_number' => null,
            'invoice_date' => now()->toDateString(),
            'status' => PurchaseInvoiceStatus::Draft,
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'other_costs' => 0,
            'total' => $subtotal,
            'notes' => 'فاتورة شراء تلقائية من تسليم الطلب '.$lockedOrder->order_number,
            'source_order_id' => $lockedOrder->id,
            'purchase_order_id' => $purchaseOrderId,
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ]);

        foreach ($lineRows as $row) {
            PurchaseInvoiceItem::query()->create([
                'purchase_invoice_id' => $invoice->id,
                ...$row,
            ]);
        }

        $this->auditLogger->record('admin.purchase_invoice.auto_created_from_delivery', $invoice, metadata: [
            'order_id' => $lockedOrder->id,
            'order_number' => $lockedOrder->order_number,
            'purchase_order_id' => $purchaseOrderId,
        ]);

        try {
            $invoice = $this->postPurchaseInvoiceAction->execute($invoice);
        } catch (\Throwable $e) {
            Log::error('Failed to post auto purchase invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $invoice->fresh(['items']);
    }
}
