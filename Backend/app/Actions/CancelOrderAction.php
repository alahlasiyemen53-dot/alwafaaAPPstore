<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CancelOrderData;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourcingStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelOrderAction
{
    public function __construct(
        private readonly ReleaseOrderInventoryAction $releaseOrderInventory,
        private readonly ReleaseCouponUsageAction $releaseCouponUsage,
        private readonly CancelSourcingAction $cancelSourcing,
        private readonly CancelPurchaseOrderAction $cancelPurchaseOrder,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationScheduler $notificationScheduler,
        private readonly NotificationMessageFactory $notificationMessageFactory,
    ) {}

    public function execute(Order $order, CancelOrderData $data, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $data, $userId): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Idempotency: if already cancelled, return safely
            if ($lockedOrder->status === OrderStatus::Cancelled) {
                return $lockedOrder;
            }

            if (! $lockedOrder->status->canBeCancelled()) {
                throw new DomainException(sprintf('لا يمكن إلغاء الطلب وهو بالحالة (%s).', $lockedOrder->status->label()));
            }

            $purchaseOrders = $lockedOrder->purchaseOrders()->lockForUpdate()->get();
            foreach ($purchaseOrders as $po) {
                if ($po->status === PurchaseOrderStatus::Collected) {
                    throw new DomainException('لا يمكن إلغاء الطلب لوجود طلبات شراء تم استلامها بالفعل من المورد وهي بحوزة المندوب حالياً.');
                }
                if ($po->status->canBeCancelled()) {
                    $this->cancelPurchaseOrder->execute($po, $data->cancellationReason, $userId);
                }
            }

            // Cancel active/pending/assigned sourcing requirements for ON_DEMAND items
            $sourcingRequirements = $lockedOrder->sourcingRequirements()
                ->lockForUpdate()
                ->get();

            foreach ($sourcingRequirements as $req) {
                if ($req->status->canBeCancelled()) {
                    $this->cancelSourcing->execute($req, $data->cancellationReason, $userId);
                } elseif ($req->status === SourcingStatus::Collected) {
                    throw new DomainException('لا يمكن إلغاء الطلب لوجود بنود تم استلامها بالفعل من المورد وهي بحوزة المندوب حالياً.');
                }
            }

            // If confirmed, release active inventory reservations and release coupon usages
            if ($lockedOrder->status === OrderStatus::Confirmed) {
                $this->releaseOrderInventory->execute($lockedOrder);
                $this->releaseCouponUsage->execute($lockedOrder, $data->cancellationReason);
            }

            $lockedOrder->update([
                'status' => OrderStatus::Cancelled,
                'cancellation_reason' => $data->cancellationReason,
                'cancelled_at' => now(),
                'cancelled_by' => $userId ?? auth()->id(),
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->auditLogger->record('admin.order.cancelled', $lockedOrder, metadata: [
                'order_number' => $lockedOrder->order_number,
                'cancellation_reason' => $data->cancellationReason,
                'cancelled_at' => $lockedOrder->cancelled_at?->toDateTimeString(),
            ]);

            $customer = Customer::query()->find($lockedOrder->customer_id);
            if ($customer !== null) {
                $notificationMessage = $this->notificationMessageFactory->forOrder(
                    NotificationType::OrderCancelled,
                    $lockedOrder->order_number,
                    OrderStatus::Cancelled,
                    (int) $lockedOrder->id,
                );
                $this->notificationScheduler->scheduleOrderNotification(
                    $customer,
                    $notificationMessage,
                    "order:{$lockedOrder->id}:order_cancelled:v1",
                    $lockedOrder->order_number
                );
            }

            return $lockedOrder->load(['customer', 'warehouse', 'currency', 'items.productVariant', 'deliveryAddress', 'reservations']);
        });
    }
}
