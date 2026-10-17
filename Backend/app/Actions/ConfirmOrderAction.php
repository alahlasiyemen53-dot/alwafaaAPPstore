<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\OrderItemData;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Support\ProductSupplierResolver;
use App\Services\AuditLogger;
use App\Services\Delivery\GovernorateDeliveryAvailability;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmOrderAction
{
    public function __construct(
        private readonly CalculateOrderTotalsAction $calculateOrderTotals,
        private readonly ReserveOrderInventoryAction $reserveOrderInventory,
        private readonly CreateOrderSourcingRequirementAction $createSourcingRequirement,
        private readonly CreatePurchaseOrdersFromOrderAction $createPurchaseOrders,
        private readonly ConsumeCouponUsageAction $consumeCouponUsage,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationScheduler $notificationScheduler,
        private readonly NotificationMessageFactory $notificationMessageFactory,
        private readonly GovernorateDeliveryAvailability $governorateDeliveryAvailability,
    ) {}

    public function execute(Order $order, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $userId): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Idempotency: if already confirmed, return safely without duplicating reservations or usages
            if ($lockedOrder->status === OrderStatus::Confirmed) {
                return $lockedOrder->loadMissing(['customer', 'warehouse', 'currency', 'items.productVariant', 'deliveryAddress', 'reservations', 'coupon']);
            }

            if ($lockedOrder->status !== OrderStatus::Draft) {
                throw new DomainException(sprintf('لا يمكن تأكيد الطلب وهو بالحالة (%s).', $lockedOrder->status->label()));
            }

            $customer = Customer::query()->findOrFail($lockedOrder->customer_id);
            if (! $customer->is_active) {
                throw new DomainException('لا يمكن تأكيد الطلب لأن العميل معطل حالياً.');
            }

            $warehouse = Warehouse::query()->findOrFail($lockedOrder->warehouse_id);
            if (! $warehouse->is_active) {
                throw new DomainException('لا يمكن تأكيد الطلب لأن المستودع معطل حالياً.');
            }

            $items = $lockedOrder->items()->with(['productVariant.supplier', 'productVariant.product.supplier'])->get();
            if ($items->isEmpty()) {
                throw new DomainException('لا يمكن تأكيد طلب لا يحتوي على بنود.');
            }

            // Recalculate authoritative prices and totals upon confirmation with concurrency locks
            $itemsData = $items->map(fn (OrderItem $i) => new OrderItemData(
                productVariantId: $i->product_variant_id,
                quantity: (float) $i->quantity,
                unitId: $i->unit_id,
            ))->all();

            $deliveryAddress = $lockedOrder->deliveryAddress()->with('governorate')->first();
            $deliveryFeeBase = (float) $lockedOrder->base_currency_delivery_fee;
            if ($deliveryAddress?->governorate !== null && $deliveryAddress->governorate->is_active) {
                $methodCode = (string) ($lockedOrder->shipping_method_code ?? 'standard');
                $assignment = $this->governorateDeliveryAvailability->resolve(
                    (int) $deliveryAddress->governorate_id,
                    $methodCode
                );
                if ($assignment === null) {
                    throw new DomainException('طريقة التوصيل المحددة غير متاحة للمحافظة المحددة.');
                }
                $deliveryFeeBase = (float) $assignment->delivery_fee;
            }

            $calculated = $this->calculateOrderTotals->execute(
                currencyId: $lockedOrder->currency_id,
                itemsData: $itemsData,
                couponCode: $lockedOrder->coupon_code_snapshot,
                customerId: $customer->id,
                now: now(),
                lockForUpdate: true,
                deliveryFeeBase: $deliveryFeeBase,
            );

            // Update item snapshots and prices
            $calculatedItemsByVariant = collect($calculated['items'])->keyBy('product_variant_id');
            foreach ($items as $item) {
                $calcItem = $calculatedItemsByVariant->get($item->product_variant_id);
                if ($calcItem) {
                    $variant = $item->productVariant;
                    $fulfillmentMode = $variant !== null ? $variant->fulfillment_mode : ProductVariantFulfillmentMode::Stocked;
                    $resolvedSupplier = $variant !== null ? ProductSupplierResolver::forVariant($variant) : null;
                    $supplierId = $resolvedSupplier?->id;
                    $supplierName = $resolvedSupplier?->name;
                    $supplierItemNumber = $variant?->supplier_item_number
                        ?: $variant?->product?->supplier_item_number;

                    $item->update([
                        'product_name_snapshot' => $calcItem['product_name_snapshot'],
                        'variant_name_snapshot' => $calcItem['variant_name_snapshot'],
                        'sku_snapshot' => $calcItem['sku_snapshot'],
                        'fulfillment_mode_snapshot' => $fulfillmentMode,
                        'sourcing_supplier_id_snapshot' => $supplierId,
                        'supplier_name_snapshot' => $supplierName,
                        'supplier_item_number_snapshot' => $supplierItemNumber,
                        'unit_id' => $calcItem['unit_id'],
                        'unit_name_snapshot' => $calcItem['unit_name_snapshot'],
                        'promotion_id' => $calcItem['promotion_id'] ?? null,
                        'promotion_name_snapshot' => $calcItem['promotion_name_snapshot'] ?? null,
                        'promotion_type_snapshot' => $calcItem['promotion_type_snapshot'] ?? null,
                        'promotion_value_base_snapshot' => $calcItem['promotion_value_base_snapshot'] ?? null,
                        'bundle_id' => $calcItem['bundle_id'] ?? null,
                        'bundle_name_snapshot' => $calcItem['bundle_name_snapshot'] ?? null,
                        'bundle_code_snapshot' => $calcItem['bundle_code_snapshot'] ?? null,
                        'bundle_pricing_type_snapshot' => $calcItem['bundle_pricing_type_snapshot'] ?? null,
                        'bundle_value_base_snapshot' => $calcItem['bundle_value_base_snapshot'] ?? null,
                        'quantity' => $calcItem['quantity'],
                        'base_quantity' => $calcItem['base_quantity'],
                        'original_unit_price' => $calcItem['original_unit_price'] ?? $calcItem['unit_price'],
                        'unit_price' => $calcItem['unit_price'],
                        'line_subtotal' => $calcItem['line_subtotal'],
                        'item_discount_amount' => $calcItem['item_discount_amount'] ?? 0.0,
                        'allocated_order_discount' => $calcItem['allocated_order_discount'] ?? 0.0,
                        'discount_amount' => $calcItem['discount_amount'],
                        'line_total' => $calcItem['line_total'],
                        'base_currency_unit_price' => $calcItem['base_currency_unit_price'],
                        'base_currency_line_total' => $calcItem['base_currency_line_total'],
                    ]);
                }
            }

            // Finalize order totals and snapshots
            $lockedOrder->update([
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'coupon_id' => $calculated['coupon_id'] ?? null,
                'coupon_code_snapshot' => $calculated['coupon_code_snapshot'] ?? null,
                'coupon_name_snapshot' => $calculated['coupon_name_snapshot'] ?? null,
                'coupon_discount_type_snapshot' => $calculated['coupon_discount_type_snapshot'] ?? null,
                'coupon_discount_value_base_snapshot' => $calculated['coupon_discount_value_base_snapshot'] ?? null,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'delivery_fee' => $calculated['delivery_fee'],
                'base_currency_delivery_fee' => $calculated['base_currency_delivery_fee'],
                'promotion_discount_total' => $calculated['promotion_discount_total'],
                'coupon_discount_total' => $calculated['coupon_discount_total'],
                'bundle_discount_total' => $calculated['bundle_discount_total'],
                'total' => $calculated['total'],
                'exchange_rate_snapshot' => $calculated['exchange_rate_snapshot'],
                'base_currency_total' => $calculated['base_currency_total'],
            ]);

            // Consume coupon usage atomically if coupon was applied
            if (! empty($calculated['coupon_id']) && $calculated['coupon_discount_total'] > 0) {
                /** @var Coupon $coupon */
                $coupon = Coupon::query()->findOrFail($calculated['coupon_id']);
                $this->consumeCouponUsage->execute($lockedOrder, $coupon, (float) $calculated['coupon_discount_total']);
            }

            // Create Purchase Orders (grouped by supplier) and legacy per-item sourcing requirements for ON_DEMAND items
            foreach ($items as $item) {
                if ($item->isOnDemand()) {
                    $this->createSourcingRequirement->execute($item);
                }
            }
            $this->createPurchaseOrders->execute($lockedOrder, $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null));

            // Reserve inventory atomically for STOCKED items (locks inventories, checks available stock, raises reserved_quantity)
            $this->reserveOrderInventory->execute($lockedOrder);

            // Mark order confirmed
            $lockedOrder->update([
                'status' => OrderStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null),
                'updated_by' => $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null),
            ]);

            $this->auditLogger->record('admin.order.confirmed', $lockedOrder, metadata: [
                'order_number' => $lockedOrder->order_number,
                'subtotal' => (float) $lockedOrder->subtotal,
                'promotion_discount_total' => (float) $lockedOrder->promotion_discount_total,
                'bundle_discount_total' => (float) $lockedOrder->bundle_discount_total,
                'coupon_discount_total' => (float) $lockedOrder->coupon_discount_total,
                'discount_amount' => (float) $lockedOrder->discount_amount,
                'coupon_code_snapshot' => $lockedOrder->coupon_code_snapshot,
                'exchange_rate_snapshot' => (string) $lockedOrder->exchange_rate_snapshot,
                'total' => (float) $lockedOrder->total,
                'base_currency_total' => (float) $lockedOrder->base_currency_total,
                'confirmed_at' => $lockedOrder->confirmed_at?->toDateTimeString(),
            ]);

            $notificationMessage = $this->notificationMessageFactory->forOrder(
                NotificationType::OrderConfirmed,
                $lockedOrder->order_number,
                OrderStatus::Confirmed,
                (int) $lockedOrder->id,
            );
            $this->notificationScheduler->scheduleOrderNotification(
                $customer,
                $notificationMessage,
                "order:{$lockedOrder->id}:order_confirmed:v1",
                $lockedOrder->order_number
            );

            return $lockedOrder->load(['customer', 'warehouse', 'currency', 'items.productVariant', 'deliveryAddress', 'reservations', 'coupon']);
        });
    }
}
