<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use InvalidArgumentException;

final class NotificationPayloadPolicy
{
    private const VERSION = 1;

    /** @return array<string, string|int> */
    public function forOrder(NotificationType $type, string $orderReference, string $status, ?int $orderId = null): array
    {
        $this->assertOrderType($type);

        if (trim($orderReference) === '' || $status !== $type->expectedOrderStatus()?->value) {
            throw new InvalidArgumentException('Notification payload is invalid for this type.');
        }

        $payload = [
            'payload_version' => self::VERSION,
            'type' => $type->payloadValue(),
            'order_reference' => $orderReference,
            'status' => $status,
        ];

        if ($orderId !== null && $orderId > 0) {
            $payload['order_id'] = (string) $orderId;
            $payload['destination_type'] = 'order';
            $payload['destination_id'] = (string) $orderId;

            if (in_array($type, [
                NotificationType::OrderAwaitingCustomerConfirmation,
                NotificationType::OrderDelivered,
            ], true)) {
                $payload['open_rating'] = '1';
            }
        }

        return $payload;
    }

    /** @return array<string, string|int> */
    public function forTest(?string $imageUrl = null, ?string $topic = null): array
    {
        $payload = [
            'payload_version' => self::VERSION,
            'type' => 'TEST_NOTIFICATION',
        ];

        $normalized = is_string($imageUrl) ? trim($imageUrl) : '';
        if ($normalized !== '') {
            $payload['image_url'] = $normalized;
        }

        $normalizedTopic = is_string($topic) ? trim($topic) : '';
        if ($normalizedTopic !== '') {
            $payload['delivery_mode'] = 'topic';
            $payload['topic'] = $normalizedTopic;
        }

        return $payload;
    }

    /** @return array<string, string|int> */
    public function forBroadcast(
        ?string $imageUrl = null,
        ?string $topic = null,
        ?string $destinationType = null,
        ?string $destinationId = null,
    ): array {
        $payload = [
            'payload_version' => self::VERSION,
            'type' => 'BROADCAST',
        ];

        $normalized = is_string($imageUrl) ? trim($imageUrl) : '';
        if ($normalized !== '') {
            $payload['image_url'] = $normalized;
        }

        $normalizedTopic = is_string($topic) ? trim($topic) : '';
        if ($normalizedTopic !== '') {
            $payload['delivery_mode'] = 'topic';
            $payload['topic'] = $normalizedTopic;
        }

        $normalizedDestType = is_string($destinationType) ? trim($destinationType) : '';
        if ($normalizedDestType !== '' && $normalizedDestType !== 'none') {
            $payload['destination_type'] = $normalizedDestType;
            if (is_string($destinationId) && trim($destinationId) !== '') {
                $payload['destination_id'] = trim($destinationId);
            }
        }

        return $payload;
    }

    /** @return array<string, string|int> */
    public function forSupportChat(string $conversationUuid, ?string $messageUuid = null): array
    {
        $conversationUuid = trim($conversationUuid);
        if ($conversationUuid === '') {
            throw new InvalidArgumentException('Support chat conversation id is required.');
        }

        $payload = [
            'payload_version' => self::VERSION,
            'type' => NotificationType::SupportChatMessage->payloadValue(),
            'conversation_id' => $conversationUuid,
            'destination_type' => 'support_chat',
            'destination_id' => $conversationUuid,
        ];

        $normalizedMessage = is_string($messageUuid) ? trim($messageUuid) : '';
        if ($normalizedMessage !== '') {
            $payload['message_id'] = $normalizedMessage;
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public function assertValid(NotificationType $type, NotificationCategory $category, NotificationChannel $channel, array $payload): void
    {
        if ($category !== $type->category() || ! in_array($channel, $type->allowedChannels(), true)) {
            throw new InvalidArgumentException('Notification type, category, and channel do not match.');
        }

        if (array_diff($type->requiredPayloadKeys(), array_keys($payload)) !== []
            || array_diff(array_keys($payload), $type->allowedPayloadKeys()) !== []) {
            throw new InvalidArgumentException('Notification payload contains unsupported data.');
        }

        $expectedPayload = match ($type) {
            NotificationType::TestNotification => $this->forTest(
                $this->optionalPayloadString($payload, 'image_url'),
                $this->optionalPayloadString($payload, 'topic'),
            ),
            NotificationType::Broadcast => $this->forBroadcast(
                $this->optionalPayloadString($payload, 'image_url'),
                $this->optionalPayloadString($payload, 'topic'),
                $this->optionalPayloadString($payload, 'destination_type'),
                $this->optionalPayloadString($payload, 'destination_id'),
            ),
            NotificationType::SupportChatMessage => $this->forSupportChat(
                $this->payloadString($payload, 'conversation_id'),
                $this->optionalPayloadString($payload, 'message_id'),
            ),
            default => $this->forOrder(
                $type,
                $this->payloadString($payload, 'order_reference'),
                $this->payloadString($payload, 'status'),
                $this->optionalOrderId($payload),
            ),
        };

        if ($payload !== $expectedPayload) {
            throw new InvalidArgumentException('Notification payload contains unsupported data.');
        }
    }

    private function assertOrderType(NotificationType $type): void
    {
        if (in_array($type, [
            NotificationType::TestNotification,
            NotificationType::Broadcast,
            NotificationType::SupportChatMessage,
        ], true)) {
            throw new InvalidArgumentException('Non-order notifications do not contain order data.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadString(array $payload, string $key): string
    {
        if (! isset($payload[$key]) || ! is_string($payload[$key])) {
            throw new InvalidArgumentException('Notification payload contains unsupported data.');
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload */
    private function optionalPayloadString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        if (! is_string($payload[$key])) {
            throw new InvalidArgumentException('Notification payload contains unsupported data.');
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload */
    private function optionalOrderId(array $payload): ?int
    {
        if (! array_key_exists('order_id', $payload)) {
            return null;
        }

        $raw = $payload['order_id'];
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        }

        throw new InvalidArgumentException('Notification payload contains unsupported data.');
    }
}
