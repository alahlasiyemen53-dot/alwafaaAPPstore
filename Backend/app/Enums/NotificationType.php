<?php

namespace App\Enums;

enum NotificationType: string
{
    case OrderConfirmed = 'order_confirmed';
    case OrderAssignedToDriver = 'order_assigned_to_driver';
    case OrderAwaitingCustomerConfirmation = 'order_awaiting_customer_confirmation';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';
    case TestNotification = 'test_notification';
    case Broadcast = 'broadcast';
    case SupportChatMessage = 'support_chat_message';

    public function category(): NotificationCategory
    {
        return $this === self::Broadcast
            ? NotificationCategory::Marketing
            : NotificationCategory::Transactional;
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::PushFcm;
    }

    /** @return list<NotificationChannel> */
    public function allowedChannels(): array
    {
        return [NotificationChannel::PushFcm];
    }

    /** @return list<string> */
    public function allowedPayloadKeys(): array
    {
        return match ($this) {
            self::TestNotification => ['payload_version', 'type', 'image_url', 'topic', 'delivery_mode'],
            self::Broadcast => ['payload_version', 'type', 'image_url', 'topic', 'delivery_mode', 'destination_type', 'destination_id'],
            self::SupportChatMessage => [
                'payload_version',
                'type',
                'conversation_id',
                'message_id',
                'destination_type',
                'destination_id',
            ],
            default => [
                'payload_version',
                'type',
                'order_reference',
                'status',
                'order_id',
                'destination_type',
                'destination_id',
                'open_rating',
            ],
        };
    }

    /** @return list<string> */
    public function requiredPayloadKeys(): array
    {
        return match ($this) {
            self::TestNotification, self::Broadcast => ['payload_version', 'type'],
            self::SupportChatMessage => [
                'payload_version',
                'type',
                'conversation_id',
                'destination_type',
                'destination_id',
            ],
            default => ['payload_version', 'type', 'order_reference', 'status'],
        };
    }

    public function expectedOrderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::OrderConfirmed => OrderStatus::Confirmed,
            self::OrderAssignedToDriver => OrderStatus::AssignedToDriver,
            self::OrderAwaitingCustomerConfirmation => OrderStatus::AwaitingCustomerConfirmation,
            self::OrderDelivered => OrderStatus::Delivered,
            self::OrderCancelled => OrderStatus::Cancelled,
            self::TestNotification, self::Broadcast, self::SupportChatMessage => null,
        };
    }

    public function payloadValue(): string
    {
        return strtoupper($this->value);
    }
}
