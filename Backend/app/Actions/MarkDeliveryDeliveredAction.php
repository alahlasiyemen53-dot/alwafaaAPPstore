<?php

namespace App\Actions;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MarkDeliveryDeliveredAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CreatePurchaseInvoiceFromDriverDeliveryAction $createPurchaseInvoiceFromDriverDelivery,
        private readonly NotificationScheduler $notificationScheduler,
        private readonly NotificationMessageFactory $notificationMessageFactory,
    ) {}

    public function execute(Delivery $delivery, ?float $codCollectedAmount = null, ?string $notes = null, int $userId = 0): Delivery
    {
        return DB::transaction(function () use ($delivery, $codCollectedAmount, $notes, $userId): Delivery {
            /** @var Delivery $lockedDelivery */
            $lockedDelivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if ($lockedDelivery->status === DeliveryStatus::Delivered) {
                return $lockedDelivery->load(['order', 'driver']);
            }

            if (! $lockedDelivery->canBeDelivered()) {
                throw new DomainException(sprintf(
                    'لا يمكن تسجيل تسليم الشحنة لأنها بالحالة (%s).',
                    $lockedDelivery->status->label()
                ));
            }

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedDelivery->order_id);

            if ($lockedOrder->status !== OrderStatus::AssignedToDriver
                && $lockedOrder->status !== OrderStatus::AwaitingCustomerConfirmation) {
                throw new DomainException('لا يمكن تسجيل التسليم إلا للطلبات الجاهزة للتوصيل.');
            }

            $now = now();
            $collectedAmount = $codCollectedAmount !== null ? $codCollectedAmount : (float) $lockedDelivery->cod_amount_to_collect;
            $deadlineHours = ConfirmCustomerDeliveryAction::confirmHours();

            $lockedDelivery->update([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => $now,
                'delivered_by' => $userId > 0 ? $userId : null,
                'cod_collected_amount' => $collectedAmount,
                'notes' => $notes ?: $lockedDelivery->notes,
            ]);

            $lockedOrder->update([
                'status' => OrderStatus::AwaitingCustomerConfirmation,
                'customer_confirm_deadline_at' => $now->copy()->addHours($deadlineHours),
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $lockedOrder->load(['items.productVariant', 'currency']);

            try {
                $this->createPurchaseInvoiceFromDriverDelivery->execute($lockedOrder, $userId);
            } catch (\Throwable $e) {
                Log::error('Auto purchase invoice failed after driver delivery', [
                    'order_id' => $lockedOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->auditLogger->record('admin.delivery.delivered', $lockedDelivery, metadata: [
                'delivery_number' => $lockedDelivery->delivery_number,
                'order_number' => $lockedOrder->order_number,
                'driver_name' => $lockedDelivery->driver?->name,
                'cod_collected' => $collectedAmount,
                'awaiting_customer_confirmation' => true,
                'customer_confirm_deadline_at' => $lockedOrder->fresh()->customer_confirm_deadline_at?->toIso8601String(),
            ]);

            $customer = Customer::query()->find($lockedOrder->customer_id);
            if ($customer !== null) {
                $notificationMessage = $this->notificationMessageFactory->forOrder(
                    NotificationType::OrderAwaitingCustomerConfirmation,
                    $lockedOrder->order_number,
                    OrderStatus::AwaitingCustomerConfirmation,
                    (int) $lockedOrder->id,
                );
                $this->notificationScheduler->scheduleOrderNotification(
                    $customer,
                    $notificationMessage,
                    "order:{$lockedOrder->id}:awaiting_customer_confirmation:v1",
                    $lockedOrder->order_number
                );
            }

            return $lockedDelivery->load(['order.customer', 'driver']);
        });
    }
}
