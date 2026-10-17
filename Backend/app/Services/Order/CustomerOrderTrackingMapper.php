<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

final class CustomerOrderTrackingMapper
{
    /** @return array{status: string, label_ar: string, occurred_at: ?string, customer_confirm_deadline_at: ?string, can_confirm_delivery: bool, can_rate: bool} */
    public function current(Order $order): array
    {
        $delivery = $order->relationLoaded('deliveries') ? $order->deliveries->sortByDesc('id')->first() : null;
        $state = match ($order->status) {
            OrderStatus::Draft => ['under_review', 'قيد المراجعة', $order->created_at],
            OrderStatus::Confirmed => ['confirmed', 'تم تأكيد طلبك', $order->confirmed_at ?? $order->created_at],
            OrderStatus::AssignedToDriver => ['ready_for_delivery', 'جاهز للتوصيل', $delivery?->assigned_at ?? $order->updated_at],
            OrderStatus::AwaitingCustomerConfirmation => ['delivered_by_courier', 'تم تسليم الطلب من قبل المندوب', $delivery?->delivered_at ?? $order->updated_at],
            OrderStatus::Delivered => ['delivered', 'مكتمل', $order->customer_confirmed_at ?? $delivery?->delivered_at],
            OrderStatus::DeliveryFailed => ['delivery_failed', 'تعذر التوصيل', $delivery?->failed_at],
            OrderStatus::ReturnedToWarehouse => ['returned', 'تم إرجاع الطلب', $delivery?->returned_at],
            OrderStatus::Cancelled => ['cancelled', 'تم إلغاء الطلب', $order->cancelled_at],
        };

        return [
            'status' => $state[0],
            'label_ar' => $state[1],
            'occurred_at' => $state[2]?->toIso8601String(),
            'customer_confirm_deadline_at' => $order->customer_confirm_deadline_at?->toIso8601String(),
            'can_confirm_delivery' => $order->status === OrderStatus::AwaitingCustomerConfirmation,
            'can_rate' => $order->status === OrderStatus::Delivered,
        ];
    }

    /**
     * Full milestone history (newest first). Stages are never dropped once reached.
     *
     * @return list<array{status: string, label_ar: string, occurred_at: string}>
     */
    public function timeline(Order $order): array
    {
        $delivery = $order->relationLoaded('deliveries')
            ? $order->deliveries->sortByDesc('id')->first()
            : null;

        $events = [];
        $this->pushEvent($events, 'created', 'تم إنشاء الطلب', $order->created_at);

        if ($this->hasReached($order, [
            OrderStatus::Confirmed,
            OrderStatus::AssignedToDriver,
            OrderStatus::AwaitingCustomerConfirmation,
            OrderStatus::Delivered,
            OrderStatus::DeliveryFailed,
            OrderStatus::ReturnedToWarehouse,
        ]) || $order->confirmed_at !== null) {
            $this->pushEvent(
                $events,
                'confirmed',
                'تم تأكيد طلبك',
                $order->confirmed_at ?? $order->created_at
            );
        }

        if ($this->hasReached($order, [
            OrderStatus::AssignedToDriver,
            OrderStatus::AwaitingCustomerConfirmation,
            OrderStatus::Delivered,
            OrderStatus::DeliveryFailed,
            OrderStatus::ReturnedToWarehouse,
        ]) || $delivery?->assigned_at !== null) {
            $this->pushEvent(
                $events,
                'ready_for_delivery',
                'جاهز للتوصيل',
                $delivery?->assigned_at ?? $order->updated_at
            );
        }

        if ($this->hasReached($order, [
            OrderStatus::AwaitingCustomerConfirmation,
            OrderStatus::Delivered,
        ]) || $delivery?->delivered_at !== null) {
            $this->pushEvent(
                $events,
                'delivered_by_courier',
                'تم تسليم الطلب من قبل المندوب',
                $delivery?->delivered_at ?? $order->updated_at
            );
        }

        if ($order->status === OrderStatus::Delivered || $order->customer_confirmed_at !== null) {
            $this->pushEvent(
                $events,
                'delivered',
                'مكتمل',
                $order->customer_confirmed_at ?? $delivery?->delivered_at ?? $order->updated_at
            );
        }

        if ($order->status === OrderStatus::DeliveryFailed || $delivery?->failed_at !== null) {
            $this->pushEvent(
                $events,
                'delivery_failed',
                'تعذر التوصيل',
                $delivery?->failed_at ?? $order->updated_at
            );
        }

        if ($order->status === OrderStatus::ReturnedToWarehouse || $delivery?->returned_at !== null) {
            $this->pushEvent(
                $events,
                'returned',
                'تم إرجاع الطلب',
                $delivery?->returned_at ?? $order->updated_at
            );
        }

        if ($order->status === OrderStatus::Cancelled || $order->cancelled_at !== null) {
            $this->pushEvent(
                $events,
                'cancelled',
                'تم إلغاء الطلب',
                $order->cancelled_at ?? $order->updated_at
            );
        }

        // Keep draft/under_review visible until confirmation exists.
        if ($order->status === OrderStatus::Draft && count($events) === 1) {
            $this->pushEvent($events, 'under_review', 'قيد المراجعة', $order->created_at);
        }

        return array_reverse($events);
    }

    /**
     * @param  list<OrderStatus>  $statuses
     */
    private function hasReached(Order $order, array $statuses): bool
    {
        return in_array($order->status, $statuses, true);
    }

    /**
     * @param  list<array{status: string, label_ar: string, occurred_at: string}>  $events
     */
    private function pushEvent(array &$events, string $status, string $labelAr, mixed $occurredAt): void
    {
        if ($occurredAt === null) {
            return;
        }

        $iso = $occurredAt instanceof CarbonInterface
            ? $occurredAt->toIso8601String()
            : (string) $occurredAt;

        if ($iso === '') {
            return;
        }

        foreach ($events as $existing) {
            if ($existing['status'] === $status) {
                return;
            }
        }

        $events[] = [
            'status' => $status,
            'label_ar' => $labelAr,
            'occurred_at' => $iso,
        ];
    }
}
