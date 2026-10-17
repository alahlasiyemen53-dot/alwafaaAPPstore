<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AssignOrderToDriverData;
use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Promote a confirmed order to ready-for-delivery when all active POs are collected
 * and a pickup driver was pre-assigned.
 */
final class PromoteOrderReadyForDeliveryAction
{
    public function __construct(
        private readonly AssignOrderToDriverAction $assignOrderToDriver,
    ) {}

    public function execute(Order $order, ?int $userId = null, ?string $notes = null): ?\App\Models\Delivery
    {
        $order->loadMissing('purchaseOrders');

        if ($order->status !== OrderStatus::Confirmed) {
            return null;
        }

        if (! $order->allActivePurchaseOrdersCollected()) {
            return null;
        }

        $driverId = $order->pickup_driver_id;
        if ($driverId === null) {
            return null;
        }

        return $this->assignOrderToDriver->execute(
            $order,
            new AssignOrderToDriverData(
                driverId: (int) $driverId,
                notes: $notes ?? 'جاهز للتوصيل بعد استلام طلبات الشراء'
            ),
            (int) ($userId ?? 0)
        );
    }
}
