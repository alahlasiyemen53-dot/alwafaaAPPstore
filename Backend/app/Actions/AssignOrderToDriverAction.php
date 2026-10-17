<?php

namespace App\Actions;

use App\DTOs\AssignOrderToDriverData;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\SourcingStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AssignOrderToDriverAction
{
    public function __construct(
        private readonly GenerateDeliveryNumberAction $generateDeliveryNumber,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationScheduler $notificationScheduler,
        private readonly NotificationMessageFactory $notificationMessageFactory,
    ) {}

    public function execute(Order $order, AssignOrderToDriverData $data, int $userId): Delivery
    {
        return DB::transaction(function () use ($order, $data, $userId): Delivery {
            // 1. Lock and validate Order
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Idempotency: if already assigned to this driver, return existing active delivery
            if ($lockedOrder->status === OrderStatus::AssignedToDriver) {
                $existing = Delivery::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('status', DeliveryStatus::Assigned)
                    ->first();

                if ($existing && $existing->driver_id === $data->driverId) {
                    return $existing->load(['order', 'driver']);
                }

                throw new DomainException('الطلب مسند بالفعل لمندوب آخر ولا يمكن إعادة إسناده مباشرة.');
            }

            if (! $lockedOrder->canBeAssignedToDriver()) {
                throw new DomainException(sprintf(
                    'لا يمكن إسناد الطلب للمندوب وهو بالحالة (%s). يجب أن يكون الطلب في حالة مؤكد (CONFIRMED).',
                    $lockedOrder->status->label()
                ));
            }

            // 2. Validate Driver
            /** @var Driver $driver */
            $driver = Driver::query()->findOrFail($data->driverId);
            if (! $driver->is_active) {
                throw new DomainException(sprintf('المندوب (%s) معطل حالياً ولا يمكن إسناد طلبات له.', $driver->name));
            }

            // 3. Verify that all active purchase orders (or legacy sourcing) are COLLECTED
            $purchaseOrders = $lockedOrder->purchaseOrders()->lockForUpdate()->get();
            if ($purchaseOrders->isNotEmpty()) {
                $blocking = $purchaseOrders->filter(
                    fn ($po) => $po->status->blocksDelivery()
                );
                if ($blocking->isNotEmpty()) {
                    throw new DomainException(sprintf(
                        'لا يمكن إسناد الطلب للتوصيل لوجود (%d) طلب/طلبات شراء لم يتم تأكيد استلامها من المورد بعد.',
                        $blocking->count()
                    ));
                }
            } else {
                $sourcingRequirements = $lockedOrder->sourcingRequirements()
                    ->lockForUpdate()
                    ->get();

                $uncollected = $sourcingRequirements->filter(fn ($r) => $r->status !== SourcingStatus::Collected);
                if ($uncollected->isNotEmpty()) {
                    throw new DomainException(sprintf(
                        'لا يمكن إسناد الطلب للتوصيل لوجود (%d) بند/بنود توفير عند الطلب لم يتم تأكيد استلامها من المورد بعد.',
                        $uncollected->count()
                    ));
                }
            }

            // 4. Lock Active Reservations for STOCKED items
            $hasStockedItems = $lockedOrder->items()->get()->contains(
                fn (OrderItem $item) => $item->isStocked() && $item->cancelled_at === null
            );

            $activeReservations = $lockedOrder->reservations()
                ->where('status', ReservationStatus::Active)
                ->lockForUpdate()
                ->get();

            if ($hasStockedItems && $activeReservations->isEmpty()) {
                throw new DomainException('الطلب لا يحتوي على أي حجوزات مخزنية نشطة للتنفيذ.');
            }

            // 4. Lock Inventories in deterministic order (warehouse_id, product_variant_id)
            $sortedReservations = $activeReservations->sortBy([
                fn ($res) => $res->warehouse_id,
                fn ($res) => $res->product_variant_id,
            ]);

            $now = now();

            foreach ($sortedReservations as $reservation) {
                /** @var Inventory $inventory */
                $inventory = Inventory::query()
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $reservedToConsume = (float) $reservation->quantity;

                // Validate inventory balances before deduction
                if ((float) $inventory->reserved_quantity < $reservedToConsume) {
                    throw new DomainException('خلل في رصيد الحجز المخزني: الكمية المحجوزة أقل من المطلوب استهلاكها.');
                }

                if ((float) $inventory->quantity < $reservedToConsume) {
                    throw new DomainException('خلل في رصيد المخزون الفعلي: الرصيد الفعلي بالمستودع لا يكفي لإتمام خروج البضاعة.');
                }

                $beforeQty = (float) $inventory->quantity;
                $afterQty = round($beforeQty - $reservedToConsume, 4);

                // A. Decrease inventories.reserved_quantity
                $inventory->reserved_quantity = round((float) $inventory->reserved_quantity - $reservedToConsume, 4);

                // B. Decrease inventories.quantity (Physical Warehouse Exit)
                $inventory->quantity = $afterQty;
                $inventory->save();

                // C. Create SALE / OUT Stock Movement
                StockMovement::create([
                    'warehouse_id' => $reservation->warehouse_id,
                    'product_variant_id' => $reservation->product_variant_id,
                    'movement_type' => StockMovementType::Sale,
                    'direction' => StockMovementDirection::Out,
                    'quantity' => $reservedToConsume,
                    'reference_type' => 'Order',
                    'reference_id' => $lockedOrder->id,
                    'reason_code' => 'order_delivery_out',
                    'notes' => sprintf('صرف مبيعات للطلب %s وتسليم لمندوب التوصيل %s (%s)', $lockedOrder->order_number, $driver->name, $driver->code),
                    'occurred_at' => $now,
                    'created_by' => $userId > 0 ? $userId : null,
                ]);

                // D. Mark Reservation as CONSUMED
                $reservation->update([
                    'status' => ReservationStatus::Consumed,
                ]);
            }

            // 5. Create Delivery record
            $deliveryNumber = $this->generateDeliveryNumber->execute();

            $delivery = Delivery::create([
                'delivery_number' => $deliveryNumber,
                'order_id' => $lockedOrder->id,
                'driver_id' => $driver->id,
                'status' => DeliveryStatus::Assigned,
                'assigned_at' => $now,
                'assigned_by' => $userId > 0 ? $userId : null,
                'cod_amount_to_collect' => $lockedOrder->payment_status === OrderPaymentStatus::Paid
                    ? 0.0
                    : (float) $lockedOrder->total,
                'cod_currency_id' => $lockedOrder->currency_id,
                'notes' => $data->notes,
            ]);

            // 6. Update Order Status
            $lockedOrder->update([
                'status' => OrderStatus::AssignedToDriver,
                'updated_by' => $userId,
            ]);

            // 7. Audit
            $this->auditLogger->record('admin.delivery.assigned', $delivery, metadata: [
                'delivery_number' => $delivery->delivery_number,
                'order_number' => $lockedOrder->order_number,
                'driver_name' => $driver->name,
                'driver_code' => $driver->code,
                'warehouse_id' => $lockedOrder->warehouse_id,
                'total_amount' => $lockedOrder->total,
            ]);

            $customer = Customer::query()->find($lockedOrder->customer_id);
            if ($customer !== null) {
                $notificationMessage = $this->notificationMessageFactory->forOrder(
                    NotificationType::OrderAssignedToDriver,
                    $lockedOrder->order_number,
                    OrderStatus::AssignedToDriver,
                    (int) $lockedOrder->id,
                );
                $this->notificationScheduler->scheduleOrderNotification(
                    $customer,
                    $notificationMessage,
                    "order:{$lockedOrder->id}:order_assigned_to_driver:v1",
                    $lockedOrder->order_number
                );
            }

            return $delivery->load(['order.customer', 'order.warehouse', 'order.currency', 'driver']);
        });
    }
}
