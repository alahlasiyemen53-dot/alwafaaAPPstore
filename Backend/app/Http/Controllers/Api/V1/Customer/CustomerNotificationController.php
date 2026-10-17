<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        $paginator = BusinessNotification::query()
            ->where('recipient_type', $customer->getMorphClass())
            ->where('recipient_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $unreadCount = BusinessNotification::query()
            ->where('recipient_type', $customer->getMorphClass())
            ->where('recipient_id', $customer->id)
            ->whereNull('read_at')
            ->count();

        $items = collect($paginator->items())->map(function (BusinessNotification $notification): array {
            $payload = is_array($notification->payload) ? $notification->payload : [];

            return [
                'id' => $notification->id,
                'type' => $notification->type?->value ?? (string) $notification->getRawOriginal('type'),
                'title' => $notification->title,
                'body' => $notification->body,
                'destination_type' => $payload['destination_type'] ?? ($payload['destination']['type'] ?? 'none'),
                'destination_id' => $payload['destination_id'] ?? ($payload['destination']['id'] ?? null),
                'conversation_id' => $payload['conversation_id'] ?? null,
                'open_rating' => ($payload['open_rating'] ?? null) === '1' || ($payload['open_rating'] ?? null) === 1,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        })->all();

        return ApiResponse::success([
            'items' => $items,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم استرجاع الإشعارات بنجاح.');
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $model = $this->ownedNotification($customer, $notification);
        if ($model === null) {
            return ApiResponse::error(
                ApiErrorCode::ResourceNotFound->defaultMessage(),
                ApiErrorCode::ResourceNotFound,
                404
            );
        }

        if ($model->read_at === null) {
            $model->forceFill(['read_at' => now()])->save();
        }

        return ApiResponse::success([
            'id' => $model->id,
            'read_at' => $model->read_at?->toIso8601String(),
        ], 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $updated = BusinessNotification::query()
            ->where('recipient_type', $customer->getMorphClass())
            ->where('recipient_id', $customer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(
            ['updated' => $updated],
            'تم تعليم جميع الإشعارات كمقروءة.'
        );
    }

    private function ownedNotification(Customer $customer, int $id): ?BusinessNotification
    {
        return BusinessNotification::query()
            ->whereKey($id)
            ->where('recipient_type', $customer->getMorphClass())
            ->where('recipient_id', $customer->id)
            ->first();
    }
}
