<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmCustomerDeliveryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EarnLoyaltyPointsAction $earnLoyaltyPointsAction,
        private readonly NotificationScheduler $notificationScheduler,
        private readonly NotificationMessageFactory $notificationMessageFactory,
    ) {}

    public function execute(Order $order, ?int $customerId = null, bool $autoClosed = false, int $userId = 0): Order
    {
        return DB::transaction(function () use ($order, $customerId, $autoClosed, $userId): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($customerId !== null && (int) $lockedOrder->customer_id !== $customerId) {
                throw new DomainException('لا يمكن تأكيد استلام طلب لا يخصك.');
            }

            if ($lockedOrder->status === OrderStatus::Delivered) {
                return $lockedOrder->load(['items', 'deliveries']);
            }

            if ($lockedOrder->status !== OrderStatus::AwaitingCustomerConfirmation) {
                throw new DomainException('لا يمكن تأكيد الاستلام في الحالة الحالية للطلب.');
            }

            $now = now();
            $lockedOrder->update([
                'status' => OrderStatus::Delivered,
                'customer_confirmed_at' => $now,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->earnLoyaltyPointsAction->execute($lockedOrder, $userId);

            $this->auditLogger->record(
                $autoClosed ? 'system.order.auto_customer_confirmed' : 'customer.order.delivery_confirmed',
                $lockedOrder,
                metadata: [
                    'order_number' => $lockedOrder->order_number,
                    'auto_closed' => $autoClosed,
                ]
            );

            if (! $autoClosed) {
                $customer = Customer::query()->find($lockedOrder->customer_id);
                if ($customer !== null) {
                    $notificationMessage = $this->notificationMessageFactory->forOrder(
                        NotificationType::OrderDelivered,
                        $lockedOrder->order_number,
                        OrderStatus::Delivered,
                        (int) $lockedOrder->id,
                    );
                    $this->notificationScheduler->scheduleOrderNotification(
                        $customer,
                        $notificationMessage,
                        "order:{$lockedOrder->id}:order_completed:v1",
                        $lockedOrder->order_number
                    );
                }
            }

            return $lockedOrder->fresh(['items', 'deliveries.driver']);
        });
    }

    public static function confirmHours(): int
    {
        $raw = Setting::query()->where('key', 'customer_delivery_confirm_hours')->value('value');

        return max(1, (int) ($raw ?: 24));
    }
}
