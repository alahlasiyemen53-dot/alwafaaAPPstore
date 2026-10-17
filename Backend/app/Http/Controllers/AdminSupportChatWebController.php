<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SupportConversationStatus;
use App\Http\Requests\Api\V1\SupportChat\StoreSupportChatMessageRequest;
use App\Models\SupportChatMessage;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\SupportChat\FirebaseCustomTokenService;
use App\Services\SupportChat\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

final class AdminSupportChatWebController extends Controller
{
    public function index(Request $request, SupportChatService $chat): View
    {
        /** @var User $admin */
        $admin = $request->user();
        $filter = $request->string('filter')->toString();
        $search = $request->string('search')->toString();
        $selected = $request->string('c')->toString();

        $page = $chat->listForAdmin($admin, [
            'filter' => $filter !== '' ? $filter : null,
            'search' => $search !== '' ? $search : null,
        ], 50);

        $bootItems = collect($page->items())
            ->map(fn (SupportConversation $conversation): array => $this->conversationPayload($conversation, $admin))
            ->values();

        $tabCounts = [
            '' => $chat->listForAdmin($admin, [], 1)->total(),
            'unread' => $chat->unreadCountForAdmin($admin),
            'mine' => $chat->listForAdmin($admin, ['filter' => 'mine'], 1)->total(),
            'closed' => $chat->listForAdmin($admin, ['filter' => 'closed'], 1)->total(),
        ];

        $quickReplies = [
            'مرحباً بك في خدمة عملاء لمّة! كيف يمكننا مساعدتك اليوم؟',
            'تم استلام طلبك وجاري التحقق من التفاصيل وسأوافيك بالرد فوراً.',
            'تم تحويل استفسارك إلى قسم العمليات والمتابعة.',
            'تم تحديث البيانات وحل المشكلة بنجاح، هل هناك أي استفسار آخر؟',
            'شكراً لتواصلك مع لمّة، نتمنى لك تجربة تسوق ممتعة!',
        ];

        return view('admin.support-chat.index', [
            'conversations' => $page,
            'filter' => $filter,
            'search' => $search,
            'selectedUuid' => $selected,
            'unreadCount' => $chat->unreadCountForAdmin($admin),
            'tabCounts' => $tabCounts,
            'quickReplies' => $quickReplies,
            'bootstrapJson' => [
                'items' => $bootItems,
                'unread_count' => $chat->unreadCountForAdmin($admin),
                'tab_counts' => $tabCounts,
                'fingerprint' => sha1($bootItems->map(fn (array $item): string => implode('|', [
                    $item['uuid'],
                    $item['status'],
                    (string) $item['last_message_at'],
                    $item['unread'] ? '1' : '0',
                    (string) $item['last_message_preview'],
                ]))->implode(';')),
            ],
            'firebaseWeb' => [
                'apiKey' => (string) config('services.firebase_web.api_key'),
                'authDomain' => (string) config('services.firebase_web.auth_domain'),
                'projectId' => (string) config('services.firebase_web.project_id'),
                'storageBucket' => (string) config('services.firebase_web.storage_bucket'),
                'messagingSenderId' => (string) config('services.firebase_web.messaging_sender_id'),
                'appId' => (string) config('services.firebase_web.app_id'),
            ],
        ]);
    }

    public function show(Request $request, string $conversation): RedirectResponse
    {
        return redirect()->route('admin.support-chat.index', array_filter([
            'c' => $conversation,
            'filter' => $request->string('filter')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ]));
    }

    public function reply(
        StoreSupportChatMessageRequest $request,
        string $conversation,
        SupportChatService $chat,
    ): JsonResponse|RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);

        $attachmentPath = null;
        $type = 'text';
        if ($request->hasFile('image')) {
            $type = 'image';
            $attachmentPath = $request->file('image')->store(
                'support-chat/'.$model->uuid,
                'local',
            );
        }

        $message = $chat->postAdminMessage(
            admin: $admin,
            conversation: $model,
            text: (string) $request->input('body', ''),
            clientMessageId: $request->input('client_message_id'),
            type: $type,
            attachmentPath: $attachmentPath,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الرد.',
                'data' => $this->messagePayload($model, $message),
            ]);
        }

        return back()->with('success', 'تم إرسال الرد.');
    }

    public function assign(Request $request, string $conversation, SupportChatService $chat): JsonResponse|RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);
        $updated = $chat->assign($admin, $model, $admin);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إسناد المحادثة إليك.',
                'conversation' => $this->conversationPayload($updated, $admin),
            ]);
        }

        return back()->with('success', 'تم إسناد المحادثة إليك.');
    }

    public function close(Request $request, string $conversation, SupportChatService $chat): JsonResponse|RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);
        $updated = $chat->close($admin, $model);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إغلاق المحادثة.',
                'conversation' => $this->conversationPayload($updated, $admin),
            ]);
        }

        return back()->with('success', 'تم إغلاق المحادثة.');
    }

    public function reopen(Request $request, string $conversation, SupportChatService $chat): JsonResponse|RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);
        $updated = $chat->reopen($admin, $model);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة فتح المحادثة.',
                'conversation' => $this->conversationPayload($updated, $admin),
            ]);
        }

        return back()->with('success', 'تم إعادة فتح المحادثة.');
    }

    public function firebaseToken(Request $request, FirebaseCustomTokenService $tokens): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        try {
            return response()->json(['data' => $tokens->issueForAdmin($admin)]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $tokens->configurationIssue() ?? $e->getMessage(),
            ], 503);
        }
    }

    public function unreadCount(Request $request, SupportChatService $chat): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        return response()->json([
            'unread_count' => $chat->unreadCountForAdmin($admin),
        ]);
    }

    public function liveIndex(Request $request, SupportChatService $chat): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $page = $chat->listForAdmin($admin, [
            'filter' => $request->string('filter')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ], 50);

        $items = collect($page->items())
            ->map(fn (SupportConversation $conversation): array => $this->conversationPayload($conversation, $admin))
            ->values();

        $fingerprint = sha1($items->map(fn (array $item): string => implode('|', [
            $item['uuid'],
            $item['status'],
            (string) $item['last_message_at'],
            $item['unread'] ? '1' : '0',
            (string) $item['last_message_preview'],
            (string) ($item['assigned_admin'] ?? ''),
        ]))->implode(';'));

        return response()->json([
            'fingerprint' => $fingerprint,
            'unread_count' => $chat->unreadCountForAdmin($admin),
            'items' => $items,
        ]);
    }

    public function liveMessages(Request $request, string $conversation, SupportChatService $chat): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);
        $messages = $model->messages()->orderBy('created_at')->orderBy('id')->limit(200)->get();

        $latestId = (int) ($messages->last()?->id ?? 0);
        if ($latestId > 0) {
            $chat->markSupportRead($admin, $model);
            $model = $model->fresh(['customer', 'assignedAdmin', 'relatedOrder']) ?? $model;
        }

        return response()->json([
            'conversation' => $this->conversationPayload($model, $admin),
            'latest_message_id' => $latestId,
            'messages' => $messages->map(
                fn (SupportChatMessage $message): array => $this->messagePayload($model, $message)
            )->values(),
            'actions' => $chat->actionLogFor($model),
            'unread_count' => $chat->unreadCountForAdmin($admin),
        ]);
    }

    public function attachment(Request $request, string $conversation, string $message, SupportChatService $chat)
    {
        /** @var User $admin */
        $admin = $request->user();
        $model = $chat->findForAdmin($admin, $conversation);
        $msg = SupportChatMessage::query()
            ->where('conversation_id', $model->id)
            ->where('uuid', $message)
            ->firstOrFail();
        if (! $msg->attachment_path || ! Storage::disk('local')->exists($msg->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($msg->attachment_path);
    }

    /** @return array<string, mixed> */
    private function conversationPayload(SupportConversation $conversation, User $admin): array
    {
        $conversation->loadMissing(['customer.tier', 'assignedAdmin', 'relatedOrder']);
        $unread = $conversation->support_last_read_at === null
            || ($conversation->last_message_at && $conversation->support_last_read_at->lt($conversation->last_message_at));

        $rawPhone = $conversation->customer?->normalized_phone ?: $conversation->customer?->phone;
        $cleanDigits = $rawPhone ? preg_replace('/[^\d]/', '', $rawPhone) : null;
        $whatsappUrl = $cleanDigits ? "https://wa.me/{$cleanDigits}" : null;

        return [
            'uuid' => $conversation->uuid,
            'status' => $conversation->status->value,
            'status_label' => $this->statusLabel($conversation->status),
            'customer_name' => $conversation->customer?->name,
            'customer_phone' => $conversation->customer?->phone,
            'customer_normalized_phone' => $conversation->customer?->normalized_phone,
            'customer_whatsapp_url' => $whatsappUrl,
            'customer_email' => $conversation->customer?->email,
            'customer_tier' => $conversation->customer?->tier?->name_ar,
            'customer_id' => $conversation->customer_id,
            'customer_url' => $conversation->customer_id ? route('admin.customers.show', $conversation->customer_id) : null,
            'assigned_admin' => $conversation->assignedAdmin?->name,
            'assigned_admin_id' => $conversation->assigned_admin_id,
            'related_order_id' => $conversation->related_order_id,
            'related_order_number' => $conversation->relatedOrder?->order_number,
            'related_order_status' => $conversation->relatedOrder?->status?->value,
            'related_order_status_label' => $conversation->relatedOrder?->status?->label() ?? ($conversation->relatedOrder?->status ? (string) $conversation->relatedOrder->status->value : null),
            'related_order_status_badge' => $conversation->relatedOrder?->status?->badgeClass() ?? 'bg-secondary-subtle text-secondary',
            'related_order_total' => $conversation->relatedOrder ? number_format((float) $conversation->relatedOrder->total, 2) : null,
            'related_order_url' => $conversation->related_order_id ? route('admin.orders.show', $conversation->related_order_id) : null,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            'last_message_human' => optional($conversation->last_message_at)?->diffForHumans(),
            'unread' => (bool) $unread,
            'is_closed' => $conversation->status === SupportConversationStatus::Closed,
            'can_assign' => $admin->hasPermission('admin.support_chat.assign'),
            'can_reply' => $admin->hasPermission('admin.support_chat.reply'),
            'can_close' => $admin->hasPermission('admin.support_chat.close'),
            'can_reopen' => $admin->hasPermission('admin.support_chat.reopen'),
        ];
    }

    /** @return array<string, mixed> */
    private function messagePayload(SupportConversation $conversation, SupportChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'sender_type' => $message->sender_type,
            'type' => $message->type,
            'body' => $message->body,
            'has_attachment' => filled($message->attachment_path),
            'attachment_url' => filled($message->attachment_path)
                ? route('admin.support-chat.attachment', [$conversation->uuid, $message->uuid])
                : null,
            'created_at' => $message->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function statusLabel(SupportConversationStatus $status): string
    {
        return match ($status) {
            SupportConversationStatus::Open => 'مفتوحة',
            SupportConversationStatus::Assigned => 'مسندة',
            SupportConversationStatus::Closed => 'مغلقة',
        };
    }
}
