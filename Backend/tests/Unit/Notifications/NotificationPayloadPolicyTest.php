<?php

namespace Tests\Unit\Notifications;

use App\Data\Notifications\PushNotificationMessageData;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Services\Notifications\NotificationPayloadPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NotificationPayloadPolicyTest extends TestCase
{
    #[DataProvider('orderPayloadCases')]
    public function test_each_initial_order_type_accepts_only_its_approved_status(NotificationType $type, string $status): void
    {
        $policy = app(NotificationPayloadPolicy::class);
        $payload = $policy->forOrder($type, 'ORD-1001', $status);

        $policy->assertValid($type, NotificationCategory::Transactional, NotificationChannel::PushFcm, $payload);

        self::assertEmpty(array_diff($type->requiredPayloadKeys(), array_keys($payload)));
        self::assertEmpty(array_diff(array_keys($payload), $type->allowedPayloadKeys()));
    }

    public function test_order_payload_with_id_includes_rating_deep_link_for_delivery_states(): void
    {
        $policy = app(NotificationPayloadPolicy::class);
        $payload = $policy->forOrder(
            NotificationType::OrderAwaitingCustomerConfirmation,
            'ORD-1001',
            'awaiting_customer_confirmation',
            42,
        );

        $policy->assertValid(
            NotificationType::OrderAwaitingCustomerConfirmation,
            NotificationCategory::Transactional,
            NotificationChannel::PushFcm,
            $payload,
        );

        self::assertSame('42', $payload['order_id']);
        self::assertSame('order', $payload['destination_type']);
        self::assertSame('42', $payload['destination_id']);
        self::assertSame('1', $payload['open_rating']);
    }

    public function test_policy_rejects_an_invalid_type_category_channel_pairing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NotificationPayloadPolicy::class)->assertValid(
            NotificationType::OrderConfirmed,
            NotificationCategory::Marketing,
            NotificationChannel::PushFcm,
            [
                'payload_version' => 1,
                'type' => 'ORDER_CONFIRMED',
                'order_reference' => 'ORD-1001',
                'status' => 'confirmed',
            ],
        );
    }

    public function test_policy_rejects_unknown_and_nested_payload_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NotificationPayloadPolicy::class)->assertValid(
            NotificationType::OrderConfirmed,
            NotificationCategory::Transactional,
            NotificationChannel::PushFcm,
            [
                'payload_version' => 1,
                'type' => 'ORDER_CONFIRMED',
                'order_reference' => ['id' => 'ORD-1001'],
                'status' => 'confirmed',
            ],
        );
    }

    public function test_message_data_rejects_an_invalid_type_category_pairing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PushNotificationMessageData(
            NotificationType::OrderConfirmed,
            NotificationCategory::Marketing,
            NotificationChannel::PushFcm,
            'تم تأكيد الطلب',
            'تم تأكيد طلبك.',
            [],
        );
    }

    /** @return array<string, array{NotificationType, string}> */
    public static function orderPayloadCases(): array
    {
        return [
            'confirmed' => [NotificationType::OrderConfirmed, 'confirmed'],
            'assigned' => [NotificationType::OrderAssignedToDriver, 'assigned_to_driver'],
            'delivered' => [NotificationType::OrderDelivered, 'delivered'],
            'cancelled' => [NotificationType::OrderCancelled, 'cancelled'],
        ];
    }
}
