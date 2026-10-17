<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourcingStatus;
use App\Models\OrderItem;
use App\Models\OrderSourcingRequirement;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\AuditLogger;
use DomainException;

final class CreateOrderSourcingRequirementAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(OrderItem $orderItem): OrderSourcingRequirement
    {
        // Idempotency: return existing if already created
        $existing = OrderSourcingRequirement::query()
            ->where('order_item_id', $orderItem->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        /** @var ProductVariant $variant */
        $variant = $orderItem->productVariant ?? ProductVariant::query()->findOrFail($orderItem->product_variant_id);

        $supplierId = $orderItem->sourcing_supplier_id_snapshot ?? $variant->supplier_id;
        if (! $supplierId) {
            throw new DomainException(sprintf(
                'لا يمكن إنشاء متطلب توفير للبند (%s) دون تحديد مورد معتمد.',
                $orderItem->sku_snapshot ?? $variant->sku
            ));
        }

        /** @var Supplier|null $supplier */
        $supplier = Supplier::query()->find($supplierId);
        if ($supplier === null || ! $supplier->is_active) {
            throw new DomainException(sprintf(
                'المورد المحدد للبند (%s) غير موجود أو معطل حالياً.',
                $orderItem->sku_snapshot ?? $variant->sku
            ));
        }

        $requirement = OrderSourcingRequirement::create([
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $variant->id,
            'supplier_id' => $supplier->id,
            'quantity' => (float) $orderItem->base_quantity,
            'status' => SourcingStatus::Pending,
        ]);

        $this->auditLogger->record('admin.sourcing.created', $requirement, metadata: [
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'variant_sku' => $variant->sku,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'quantity' => (float) $orderItem->base_quantity,
            'status' => SourcingStatus::Pending->value,
        ]);

        return $requirement;
    }
}
