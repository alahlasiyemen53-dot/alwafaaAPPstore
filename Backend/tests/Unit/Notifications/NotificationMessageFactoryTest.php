<?php

namespace Tests\Unit\Notifications;

use App\Data\Notifications\TestNotificationData;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Services\Notifications\NotificationMessageFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NotificationMessageFactoryTest extends TestCase
{
    #[DataProvider('orderMessageCases')]
    public function test_order_messages_have_customer_safe_arabic_content(
        NotificationType $type,
        OrderStatus $status,
        string $title,
        string $body,
    ): void {
        $message = app(NotificationMessageFactory::class)->forOrder($type, 'ORD-1001', $status);

        self::assertSame($title, $message->title);
        self::assertSame($body, $message->body);
        self::assertSame($type->category(), $message->category);
        self::assertSame($type->channel(), $message->channel);
        self::assertSame($type->payloadValue(), $message->payload['type']);
        self::assertSame($status->value, $message->payload['status']);
    }

    public function test_order_message_rejects_a_notification_type_with_the_wrong_status(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NotificationMessageFactory::class)->forOrder(
            NotificationType::OrderDelivered,
            'ORD-1001',
            OrderStatus::Confirmed,
        );
    }

    public function test_test_notification_has_no_arbitrary_payload_fields(): void
    {
        $message = app(NotificationMessageFactory::class)->test(new TestNotificationData('اختبار الإشعارات', 'هذه رسالة اختبار.'));

        self::assertSame(NotificationType::TestNotification, $message->type);
        self::assertSame(['payload_version' => 1, 'type' => 'TEST_NOTIFICATION'], $message->payload);
    }

    public function test_test_notification_can_include_optional_image_url(): void
    {
        $message = app(NotificationMessageFactory::class)->test(new TestNotificationData(
            'اختبار بصورة',
            'رسالة مع صورة',
            'https://cdn.example.com/promo.jpg',
        ));

        self::assertSame('https://cdn.example.com/promo.jpg', $message->payload['image_url']);
    }

    public function test_notification_content_enforces_the_persistence_limits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NotificationMessageFactory::class)->test(new TestNotificationData(str_repeat('ع', 121), 'رسالة اختبار'));
    }

    /** @return array<string, array{NotificationType, OrderStatus, string, string}> */
    public static function orderMessageCases(): array
    {
        return [
            'confirmed' => [NotificationType::OrderConfirmed, OrderStatus::Confirmed, 'تم تأكيد الطلب', 'تم تأكيد طلبك رقم ORD-1001.'],
            'assigned' => [NotificationType::OrderAssignedToDriver, OrderStatus::AssignedToDriver, 'طلبك جاهز للتوصيل', 'طلبك رقم ORD-1001 جاهز للتوصيل.'],
            'awaiting_confirmation' => [
                NotificationType::OrderAwaitingCustomerConfirmation,
                OrderStatus::AwaitingCustomerConfirmation,
                'تم التسليم من المندوب',
                'تم تسليم طلبك رقم ORD-1001. يرجى تقييم الأصناف وتقييم المندوب من التطبيق.',
            ],
            'delivered' => [
                NotificationType::OrderDelivered,
                OrderStatus::Delivered,
                'اكتمل الطلب',
                'تم تأكيد استلام طلبك رقم ORD-1001. يمكنك تقييم الأصناف والمندوب الآن.',
            ],
            'cancelled' => [NotificationType::OrderCancelled, OrderStatus::Cancelled, 'تم إلغاء الطلب', 'تم إلغاء طلبك رقم ORD-1001.'],
        ];
    }
}
