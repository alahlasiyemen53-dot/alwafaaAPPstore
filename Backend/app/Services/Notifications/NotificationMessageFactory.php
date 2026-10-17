<?php

namespace App\Services\Notifications;

use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\TestNotificationData;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use InvalidArgumentException;

final class NotificationMessageFactory
{
    public function __construct(private readonly NotificationPayloadPolicy $payloadPolicy) {}

    public function forOrder(
        NotificationType $type,
        string $orderReference,
        OrderStatus $status,
        ?int $orderId = null,
    ): PushNotificationMessageData {
        $this->assertStatusMatchesType($type, $status);

        [$title, $body] = $this->contentFor($type, $orderReference);

        return new PushNotificationMessageData(
            $type,
            $type->category(),
            $type->channel(),
            $title,
            $body,
            $this->payloadPolicy->forOrder($type, $orderReference, $status->value, $orderId),
        );
    }

    public function test(TestNotificationData $notification): PushNotificationMessageData
    {
        $type = NotificationType::TestNotification;

        return new PushNotificationMessageData(
            $type,
            $type->category(),
            $type->channel(),
            $notification->title,
            $notification->body,
            $this->payloadPolicy->forTest($notification->imageUrl),
        );
    }

    public function broadcast(
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?string $destinationType = null,
        ?string $destinationId = null,
    ): PushNotificationMessageData {
        $type = NotificationType::Broadcast;

        return new PushNotificationMessageData(
            $type,
            $type->category(),
            $type->channel(),
            $title,
            $body,
            $this->payloadPolicy->forBroadcast(
                $imageUrl,
                destinationType: $destinationType,
                destinationId: $destinationId,
            ),
        );
    }

    public function forSupportChatReply(
        string $conversationUuid,
        string $preview,
        ?string $messageUuid = null,
    ): PushNotificationMessageData {
        $type = NotificationType::SupportChatMessage;
        $trimmed = trim($preview);
        $body = $trimmed !== ''
            ? mb_substr($trimmed, 0, 120)
            : 'لديك رد جديد من فريق دعم لمّة.';

        return new PushNotificationMessageData(
            $type,
            $type->category(),
            $type->channel(),
            'رد من دعم لمّة',
            $body,
            $this->payloadPolicy->forSupportChat($conversationUuid, $messageUuid),
        );
    }

    /** @return array{string, string} */
    private function contentFor(NotificationType $type, string $orderReference): array
    {
        return match ($type) {
            NotificationType::OrderConfirmed => ['تم تأكيد الطلب', "تم تأكيد طلبك رقم {$orderReference}."],
            NotificationType::OrderAssignedToDriver => ['طلبك جاهز للتوصيل', "طلبك رقم {$orderReference} جاهز للتوصيل."],
            NotificationType::OrderAwaitingCustomerConfirmation => [
                'تم التسليم من المندوب',
                "تم تسليم طلبك رقم {$orderReference}. يرجى تقييم الأصناف وتقييم المندوب من التطبيق.",
            ],
            NotificationType::OrderDelivered => [
                'اكتمل الطلب',
                "تم تأكيد استلام طلبك رقم {$orderReference}. يمكنك تقييم الأصناف والمندوب الآن.",
            ],
            NotificationType::OrderCancelled => ['تم إلغاء الطلب', "تم إلغاء طلبك رقم {$orderReference}."],
            NotificationType::TestNotification,
            NotificationType::Broadcast,
            NotificationType::SupportChatMessage => throw new InvalidArgumentException('Use a dedicated factory method for this notification type.'),
        };
    }

    private function assertStatusMatchesType(NotificationType $type, OrderStatus $status): void
    {
        $expectedStatus = $type->expectedOrderStatus();

        if ($status !== $expectedStatus) {
            throw new InvalidArgumentException('Notification type does not match the order status.');
        }
    }
}
