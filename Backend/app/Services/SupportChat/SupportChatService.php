<?php

declare(strict_types=1);

namespace App\Services\SupportChat;

use App\Contracts\SupportChat\SupportChatFirestoreGateway;
use App\Enums\SupportConversationStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportChatMessage;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class SupportChatService
{
    public function __construct(
        private readonly SupportChatFirestoreGateway $firestore,
        private readonly AuditLogger $audit,
        private readonly NotificationMessageFactory $notificationMessages,
        private readonly NotificationScheduler $notificationScheduler,
    ) {}

    public function createConversation(Customer $customer, ?int $relatedOrderId = null): SupportConversation
    {
        $orderId = null;
        if ($relatedOrderId !== null) {
            $order = Order::query()
                ->where('id', $relatedOrderId)
                ->where('customer_id', $customer->id)
                ->first();
            if ($order === null) {
                throw ValidationException::withMessages([
                    'related_order_id' => ['الطلب غير موجود أو لا يخص حسابك.'],
                ]);
            }
            $orderId = $order->id;
        }

        $existing = SupportConversation::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            if ($existing->status === SupportConversationStatus::Closed) {
                $existing->status = SupportConversationStatus::Open;
                $existing->closed_at = null;
            }
            if ($orderId !== null && $existing->related_order_id === null) {
                $existing->related_order_id = $orderId;
            }
            $existing->save();
            $this->safeSyncConversation($existing);

            return $existing->fresh(['customer', 'relatedOrder']);
        }

        $conversation = SupportConversation::query()->create([
            'customer_id' => $customer->id,
            'related_order_id' => $orderId,
            'status' => SupportConversationStatus::Open,
        ]);

        $this->safeSyncConversation($conversation);
        $this->audit->record('support_chat.conversation.created', $conversation, null, [
            'customer_id' => $customer->id,
            'related_order_id' => $orderId,
        ]);

        return $conversation->fresh(['customer', 'relatedOrder']);
    }

    /** @return LengthAwarePaginator<int, SupportConversation> */
    public function listForCustomer(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return SupportConversation::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(1);
    }

    public function findForCustomer(Customer $customer, string $uuid): SupportConversation
    {
        $conversation = SupportConversation::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $customer->id)
            ->first();

        if ($conversation === null) {
            throw ValidationException::withMessages([
                'conversation' => ['المحادثة غير موجودة.'],
            ]);
        }

        return $conversation;
    }

    /**
     * @param  array{filter?: string, search?: string}  $filters
     * @return LengthAwarePaginator<int, SupportConversation>
     */
    public function unreadCountForAdmin(User $admin): int
    {
        $query = SupportConversation::query();
        $this->applyAdminVisibility($query, $admin);
        $query->where(function (Builder $q): void {
            $q->whereNull('support_last_read_at')
                ->orWhereColumn('support_last_read_at', '<', 'last_message_at');
        });

        return (int) $query->count();
    }

    /** @return list<array{action: string, label: string, actor: ?string, at: ?string, human: ?string}> */
    public function actionLogFor(SupportConversation $conversation, int $limit = 20): array
    {
        $actions = [
            'support_chat.conversation.created' => 'إنشاء المحادثة',
            'support_chat.conversation.assigned' => 'إسناد المحادثة',
            'support_chat.conversation.closed' => 'إغلاق المحادثة',
            'support_chat.conversation.reopened' => 'إعادة فتح المحادثة',
        ];

        return AuditLog::query()
            ->with('actor')
            ->where('subject_type', $conversation->getMorphClass())
            ->where('subject_id', $conversation->getKey())
            ->whereIn('action', array_keys($actions))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(static fn (AuditLog $log): array => [
                'action' => $log->action,
                'label' => $actions[$log->action] ?? $log->action,
                'actor' => $log->actor?->name,
                'at' => $log->created_at?->toIso8601String(),
                'human' => $log->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    public function listForAdmin(User $admin, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SupportConversation::query()
            ->with(['customer', 'assignedAdmin', 'relatedOrder'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applyAdminVisibility($query, $admin);

        $filter = $filters['filter'] ?? null;
        match ($filter) {
            'open' => $query->where('status', SupportConversationStatus::Open),
            'assigned' => $query->where('status', SupportConversationStatus::Assigned),
            'closed' => $query->where('status', SupportConversationStatus::Closed),
            'assigned_to_me', 'mine' => $query->where('assigned_admin_id', $admin->id),
            'unread' => $query->where(function (Builder $q): void {
                $q->whereNull('support_last_read_at')
                    ->orWhereColumn('support_last_read_at', '<', 'last_message_at');
            }),
            default => null,
        };

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('uuid', 'like', $term)
                    ->orWhere('last_message_preview', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('phone', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('normalized_phone', 'like', $term));
            });
        }

        return $query->paginate($perPage);
    }

    public function findForAdmin(User $admin, string $uuid): SupportConversation
    {
        $query = SupportConversation::query()->where('uuid', $uuid);
        $this->applyAdminVisibility($query, $admin);
        $conversation = $query->first();

        if ($conversation === null) {
            throw ValidationException::withMessages([
                'conversation' => ['المحادثة غير موجودة أو غير مسموح بها.'],
            ]);
        }

        return $conversation->load(['customer', 'assignedAdmin', 'relatedOrder']);
    }

    public function postCustomerMessage(
        Customer $customer,
        SupportConversation $conversation,
        string $text,
        ?string $clientMessageId = null,
        string $type = 'text',
        ?string $attachmentPath = null,
    ): SupportChatMessage {
        if ($conversation->customer_id !== $customer->id) {
            throw new RuntimeException('Forbidden conversation.');
        }
        if (! $conversation->status->allowsCustomerMessage()) {
            throw ValidationException::withMessages([
                'conversation' => ['المحادثة مغلقة. اطلب إعادة الفتح من الدعم.'],
            ]);
        }

        return $this->persistMessage(
            conversation: $conversation,
            senderType: 'customer',
            senderId: $customer->id,
            text: $text,
            clientMessageId: $clientMessageId,
            type: $type,
            attachmentPath: $attachmentPath,
            bumpCustomerRead: true,
        );
    }

    public function postAdminMessage(
        User $admin,
        SupportConversation $conversation,
        string $text,
        ?string $clientMessageId = null,
        string $type = 'text',
        ?string $attachmentPath = null,
    ): SupportChatMessage {
        $this->assertAdminCanAccess($admin, $conversation);
        if (! $conversation->status->allowsSupportMessage()) {
            throw ValidationException::withMessages([
                'conversation' => ['المحادثة مغلقة. أعد فتحها قبل الرد.'],
            ]);
        }

        if ($conversation->assigned_admin_id === null) {
            $this->assign($admin, $conversation, $admin);
        }

        $conversation = $conversation->fresh(['customer']) ?? $conversation;

        $message = $this->persistMessage(
            conversation: $conversation,
            senderType: 'admin',
            senderId: $admin->id,
            text: $text,
            clientMessageId: $clientMessageId,
            type: $type,
            attachmentPath: $attachmentPath,
            bumpSupportRead: true,
        );

        $this->notifyCustomerOfAdminReply($conversation, $message);

        return $message;
    }

    public function markCustomerRead(Customer $customer, SupportConversation $conversation): SupportConversation
    {
        if ($conversation->customer_id !== $customer->id) {
            throw new RuntimeException('Forbidden conversation.');
        }

        $now = now();
        if ($conversation->customer_last_read_at === null || $conversation->customer_last_read_at->lt($now)) {
            $conversation->customer_last_read_at = $now;
            $conversation->save();
            $this->safeSyncConversation($conversation);
        }

        return $conversation->fresh();
    }

    public function markSupportRead(User $admin, SupportConversation $conversation): SupportConversation
    {
        $this->assertAdminCanAccess($admin, $conversation);
        $now = now();
        if ($conversation->support_last_read_at === null || $conversation->support_last_read_at->lt($now)) {
            $conversation->support_last_read_at = $now;
            $conversation->save();
            $this->safeSyncConversation($conversation);
        }

        return $conversation->fresh();
    }

    public function assign(User $actor, SupportConversation $conversation, User $assignee): SupportConversation
    {
        $this->assertAdminCanAccess($actor, $conversation);

        return DB::transaction(function () use ($actor, $conversation, $assignee): SupportConversation {
            /** @var SupportConversation $locked */
            $locked = SupportConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $locked->assigned_admin_id = $assignee->id;
            $locked->status = SupportConversationStatus::Assigned;
            $locked->save();

            $this->audit->record('support_chat.conversation.assigned', $locked, null, [
                'actor_admin_id' => $actor->id,
                'assignee_admin_id' => $assignee->id,
            ]);

            $this->safeSyncConversation($locked);

            return $locked->fresh(['assignedAdmin', 'customer']);
        });
    }

    public function close(User $admin, SupportConversation $conversation): SupportConversation
    {
        $this->assertAdminCanAccess($admin, $conversation);
        $conversation->status = SupportConversationStatus::Closed;
        $conversation->save();
        $this->audit->record('support_chat.conversation.closed', $conversation, null, [
            'actor_admin_id' => $admin->id,
        ]);
        $this->safeSyncConversation($conversation);

        return $conversation->fresh();
    }

    public function reopen(User $admin, SupportConversation $conversation): SupportConversation
    {
        $this->assertAdminCanAccess($admin, $conversation);
        $conversation->status = $conversation->assigned_admin_id
            ? SupportConversationStatus::Assigned
            : SupportConversationStatus::Open;
        $conversation->save();
        $this->audit->record('support_chat.conversation.reopened', $conversation, null, [
            'actor_admin_id' => $admin->id,
        ]);
        $this->safeSyncConversation($conversation);

        return $conversation->fresh();
    }

    private function persistMessage(
        SupportConversation $conversation,
        string $senderType,
        int $senderId,
        string $text,
        ?string $clientMessageId,
        string $type,
        ?string $attachmentPath,
        bool $bumpCustomerRead = false,
        bool $bumpSupportRead = false,
    ): SupportChatMessage {
        $text = trim($text);
        if ($type === 'text' && $text === '') {
            throw ValidationException::withMessages(['body' => ['نص الرسالة مطلوب.']]);
        }
        if (mb_strlen($text) > 4000) {
            throw ValidationException::withMessages(['body' => ['الرسالة طويلة جداً.']]);
        }

        if ($clientMessageId) {
            $existing = SupportChatMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_type', $senderType)
                ->where('sender_id', $senderId)
                ->where('client_message_id', $clientMessageId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $message = DB::transaction(function () use (
            $conversation,
            $senderType,
            $senderId,
            $text,
            $clientMessageId,
            $type,
            $attachmentPath,
            $bumpCustomerRead,
            $bumpSupportRead,
        ): SupportChatMessage {
            $message = SupportChatMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'type' => $type,
                'body' => $text !== '' ? $text : null,
                'attachment_path' => $attachmentPath,
                'client_message_id' => $clientMessageId,
            ]);

            $preview = $type === 'image'
                ? '[صورة]'
                : mb_substr($text, 0, 120);

            $conversation->last_message_at = $message->created_at;
            $conversation->last_message_preview = $preview;
            if ($bumpCustomerRead) {
                $conversation->customer_last_read_at = $message->created_at;
            }
            if ($bumpSupportRead) {
                $conversation->support_last_read_at = $message->created_at;
            }
            $conversation->save();

            return $message;
        });

        $this->safeSyncMessage($conversation->fresh(), $message);

        return $message;
    }

    private function applyAdminVisibility(Builder $query, User $admin): void
    {
        if ($admin->hasRole('super_admin') || $admin->hasPermission('admin.support_chat.view_all')) {
            return;
        }

        $query->where(function (Builder $q) use ($admin): void {
            $q->whereNull('assigned_admin_id')
                ->orWhere('assigned_admin_id', $admin->id);
        });
    }

    private function assertAdminCanAccess(User $admin, SupportConversation $conversation): void
    {
        if ($admin->hasRole('super_admin') || $admin->hasPermission('admin.support_chat.view_all')) {
            return;
        }

        if (
            $conversation->assigned_admin_id !== null
            && (int) $conversation->assigned_admin_id !== (int) $admin->id
        ) {
            throw ValidationException::withMessages([
                'conversation' => ['هذه المحادثة مسندة لوكيل آخر.'],
            ]);
        }
    }

    private function safeSyncConversation(SupportConversation $conversation): void
    {
        try {
            $this->firestore->syncConversation($conversation);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function safeSyncMessage(SupportConversation $conversation, SupportChatMessage $message): void
    {
        try {
            $this->firestore->syncMessage($conversation, $message);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function notifyCustomerOfAdminReply(
        SupportConversation $conversation,
        SupportChatMessage $message,
    ): void {
        $customer = $conversation->customer;
        if ($customer === null) {
            return;
        }

        $preview = $message->type === 'image'
            ? '[صورة]'
            : (string) ($message->body ?? '');

        try {
            $notification = $this->notificationMessages->forSupportChatReply(
                $conversation->uuid,
                $preview,
                $message->uuid,
            );
            $this->notificationScheduler->scheduleOrderNotification(
                $customer,
                $notification,
                "support_chat:{$conversation->id}:message:{$message->id}:v1",
                $conversation->uuid,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
