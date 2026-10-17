<?php

declare(strict_types=1);

namespace App\Infrastructure\SupportChat;

use App\Contracts\SupportChat\SupportChatFirestoreGateway;
use App\Models\SupportChatMessage;
use App\Models\SupportConversation;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Factory;
use Throwable;

final class KreaitSupportChatFirestoreGateway implements SupportChatFirestoreGateway
{
    public function __construct(
        private readonly ?FirestoreClient $firestore,
        private readonly ?string $disabledReason = null,
    ) {}

    /** @param array<string, mixed> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        if (! ($configuration['enabled'] ?? false)) {
            return new self(null, 'FCM/Firebase is disabled.');
        }

        $path = $configuration['credentials_path'] ?? null;
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return new self(null, 'Firebase credentials missing.');
        }

        try {
            $factory = (new Factory)->withServiceAccount($path);
            $firestore = $factory->createFirestore()->database();

            return new self($firestore);
        } catch (Throwable $e) {
            report($e);

            return new self(null, 'Firebase Firestore could not be initialized.');
        }
    }

    public function isReady(): bool
    {
        return $this->firestore !== null;
    }

    public function syncConversation(SupportConversation $conversation): void
    {
        if ($this->firestore === null) {
            return;
        }

        $memberUids = [$conversation->customerFirebaseUid()];
        if ($conversation->assigned_admin_id) {
            $memberUids[] = 'admin:'.$conversation->assigned_admin_id;
        }

        $this->firestore
            ->collection('support_conversations')
            ->document($conversation->uuid)
            ->set([
                'customer_id' => $conversation->customer_id,
                'assigned_admin_id' => $conversation->assigned_admin_id,
                'related_order_id' => $conversation->related_order_id,
                'status' => $conversation->status->value,
                'member_uids' => $memberUids,
                'last_message_at' => $conversation->last_message_at?->getTimestamp(),
                'last_message_preview' => $conversation->last_message_preview,
                'customer_last_read_at' => $conversation->customer_last_read_at?->getTimestamp(),
                'support_last_read_at' => $conversation->support_last_read_at?->getTimestamp(),
                'updated_at' => $conversation->updated_at?->getTimestamp(),
                'created_at' => $conversation->created_at?->getTimestamp(),
            ], ['merge' => true]);
    }

    public function syncMessage(SupportConversation $conversation, SupportChatMessage $message): void
    {
        if ($this->firestore === null) {
            return;
        }

        $this->syncConversation($conversation);

        $this->firestore
            ->collection('support_conversations')
            ->document($conversation->uuid)
            ->collection('messages')
            ->document($message->uuid)
            ->set([
                'sender_uid' => $message->senderFirebaseUid(),
                'sender_type' => $message->sender_type,
                'type' => $message->type,
                'text' => $message->body,
                'attachment_path' => $message->attachment_path,
                'client_message_id' => $message->client_message_id,
                'created_at' => $message->created_at?->getTimestamp(),
            ], ['merge' => true]);
    }
}
