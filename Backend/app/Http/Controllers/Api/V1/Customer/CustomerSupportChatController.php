<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SupportChat\StoreSupportChatMessageRequest;
use App\Http\Requests\Api\V1\SupportChat\StoreSupportConversationRequest;
use App\Http\Resources\Api\V1\SupportChat\SupportChatMessageResource;
use App\Http\Resources\Api\V1\SupportChat\SupportConversationResource;
use App\Models\Customer;
use App\Models\SupportChatMessage;
use App\Services\SupportChat\FirebaseCustomTokenService;
use App\Services\SupportChat\SupportChatService;
use App\Enums\ApiErrorCode;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CustomerSupportChatController extends Controller
{
    public function firebaseToken(Request $request, FirebaseCustomTokenService $tokens): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $issued = $tokens->issueForCustomer($customer);
        } catch (Throwable $e) {
            return ApiResponse::error(
                $tokens->configurationIssue() ?? 'تعذر إصدار رمز Firebase.',
                ApiErrorCode::InternalError,
                503,
            );
        }

        return ApiResponse::success($issued, 'تم إصدار رمز Firebase.');
    }

    public function index(Request $request, SupportChatService $chat): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $page = $chat->listForCustomer($customer);

        return ApiResponse::success([
            'items' => SupportConversationResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(
        StoreSupportConversationRequest $request,
        SupportChatService $chat,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $conversation = $chat->createConversation(
            $customer,
            $request->integer('related_order_id') ?: null,
        );

        return ApiResponse::created(
            SupportConversationResource::make($conversation)->resolve(),
            'تم إنشاء محادثة الدعم.',
        );
    }

    public function show(Request $request, string $conversation, SupportChatService $chat): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $model = $chat->findForCustomer($customer, $conversation);

        return ApiResponse::success(SupportConversationResource::make($model)->resolve());
    }

    public function messages(Request $request, string $conversation, SupportChatService $chat): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $model = $chat->findForCustomer($customer, $conversation);

        $before = $request->query('before');
        $query = $model->messages()->orderByDesc('created_at')->orderByDesc('id');
        if (is_string($before) && $before !== '') {
            $cursor = SupportChatMessage::query()
                ->where('conversation_id', $model->id)
                ->where('uuid', $before)
                ->first();
            if ($cursor) {
                $query->where(function ($q) use ($cursor): void {
                    $q->where('created_at', '<', $cursor->created_at)
                        ->orWhere(function ($q2) use ($cursor): void {
                            $q2->where('created_at', $cursor->created_at)
                                ->where('id', '<', $cursor->id);
                        });
                });
            }
        }

        $items = $query->limit(50)->get()->sortBy('created_at')->values();

        return ApiResponse::success([
            'items' => SupportChatMessageResource::collection($items)->resolve(),
        ]);
    }

    public function storeMessage(
        StoreSupportChatMessageRequest $request,
        string $conversation,
        SupportChatService $chat,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $model = $chat->findForCustomer($customer, $conversation);

        $attachmentPath = null;
        $type = 'text';
        if ($request->hasFile('image')) {
            $type = 'image';
            $attachmentPath = $request->file('image')->store(
                'support-chat/'.$model->uuid,
                'local',
            );
        }

        try {
            $message = $chat->postCustomerMessage(
                customer: $customer,
                conversation: $model,
                text: (string) $request->input('body', ''),
                clientMessageId: $request->input('client_message_id'),
                type: $type,
                attachmentPath: $attachmentPath,
            );
        } catch (RuntimeException) {
            return ApiResponse::error('غير مصرح.', ApiErrorCode::Forbidden, 403);
        }

        return ApiResponse::created(
            SupportChatMessageResource::make($message)->resolve(),
            'تم إرسال الرسالة.',
        );
    }

    public function markRead(Request $request, string $conversation, SupportChatService $chat): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $model = $chat->findForCustomer($customer, $conversation);
        $updated = $chat->markCustomerRead($customer, $model);

        return ApiResponse::success(SupportConversationResource::make($updated)->resolve());
    }

    public function attachment(Request $request, string $conversation, string $message, SupportChatService $chat)
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $model = $chat->findForCustomer($customer, $conversation);
        $msg = $model->messages()->where('uuid', $message)->firstOrFail();
        if (! $msg->attachment_path || ! Storage::disk('local')->exists($msg->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($msg->attachment_path);
    }
}
