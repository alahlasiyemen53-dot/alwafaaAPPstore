<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Order;

use App\Actions\ConfirmCustomerDeliveryAction;
use App\Actions\ReorderOrderToCartAction;
use App\Actions\SubmitOrderRatingsAction;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Order\CustomerOrderDetailResource;
use App\Http\Resources\Api\V1\Order\CustomerOrderSummaryResource;
use App\Http\Resources\Api\V1\Order\CustomerOrderTrackingResource;
use App\Models\Customer;
use App\Models\DeliveryRating;
use App\Models\Order;
use App\Models\ProductReview;
use App\Queries\Order\CustomerOrderQuery;
use App\Services\Order\CustomerOrderTrackingMapper;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQuery $orders,
        private readonly CustomerOrderTrackingMapper $trackingMapper,
        private readonly ReorderOrderToCartAction $reorderAction,
        private readonly ConfirmCustomerDeliveryAction $confirmCustomerDeliveryAction,
        private readonly SubmitOrderRatingsAction $submitOrderRatingsAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $orders = $this->orders->paginate($customer, (int) $request->input('per_page', 15), (int) $request->input('page', 1));

        return ApiResponse::success([
            'items' => $orders->getCollection()->map(fn ($order) => (new CustomerOrderSummaryResource($order, $this->trackingMapper))->resolve())->all(),
            'pagination' => ['current_page' => $orders->currentPage(), 'per_page' => $orders->perPage(), 'total' => $orders->total(), 'last_page' => $orders->lastPage(), 'has_more' => $orders->hasMorePages()],
        ], 'تم استرجاع الطلبات بنجاح.');
    }

    public function show(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);

        return $customerOrder === null ? $this->notFound() : ApiResponse::success(new CustomerOrderDetailResource($customerOrder, $this->trackingMapper), 'تم استرجاع تفاصيل الطلب بنجاح.');
    }

    public function tracking(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);

        return $customerOrder === null ? $this->notFound() : ApiResponse::success(new CustomerOrderTrackingResource($customerOrder, $this->trackingMapper), 'تم استرجاع تتبع الطلب بنجاح.');
    }

    public function confirmDelivery(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);
        if ($customerOrder === null) {
            return $this->notFound();
        }

        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        try {
            $updated = $this->confirmCustomerDeliveryAction->execute($customerOrder, $customer->id);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), ApiErrorCode::ValidationFailed, 422);
        }

        return ApiResponse::success(
            new CustomerOrderDetailResource($updated, $this->trackingMapper),
            'تم تأكيد استلام الطلب بنجاح.'
        );
    }

    public function submitRatings(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);
        if ($customerOrder === null) {
            return $this->notFound();
        }

        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $validated = $request->validate([
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['nullable', 'integer'],
            'products.*.order_item_id' => ['nullable', 'integer'],
            'products.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'products.*.body' => ['nullable', 'string', 'max:2000'],
            'driver_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'driver_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->submitOrderRatingsAction->execute(
                $customerOrder,
                $customer->id,
                $validated['products'] ?? [],
                isset($validated['driver_rating']) ? (int) $validated['driver_rating'] : null,
                $validated['driver_comment'] ?? null,
            );
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), ApiErrorCode::ValidationFailed, 422);
        }

        return ApiResponse::success($result, 'تم حفظ التقييم بنجاح.');
    }

    public function ratingsStatus(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);
        if ($customerOrder === null) {
            return $this->notFound();
        }

        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $productIds = $customerOrder->items
            ->map(fn ($item) => (int) ($item->productVariant?->product_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $reviewedProductIds = ProductReview::query()
            ->where('customer_id', $customer->id)
            ->where('order_id', $customerOrder->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();

        $driverRated = DeliveryRating::query()->where('order_id', $customerOrder->id)->exists();

        return ApiResponse::success([
            'can_rate' => $customerOrder->status->value === 'delivered',
            'reviewed_product_ids' => $reviewedProductIds,
            'driver_rated' => $driverRated,
            'is_fully_rated' => $driverRated && count($reviewedProductIds) >= $productIds->count(),
        ], 'تم استرجاع حالة التقييم.');
    }

    public function reorder(int $order, Request $request): JsonResponse
    {
        $customerOrder = $this->ownedOrder($request, $order);
        if ($customerOrder === null) {
            return $this->notFound();
        }

        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $report = $this->reorderAction->execute($customer, $customerOrder);

        $message = $report['added_count'] > 0
            ? ($report['skipped_count'] > 0
                ? 'تمت إضافة المنتجات المتاحة إلى السلة. بعض المنتجات لم تعد متاحة.'
                : 'تمت إضافة المنتجات المتاحة إلى السلة.')
            : 'تعذر إضافة منتجات هذا الطلب إلى السلة.';

        return ApiResponse::success($report, $message);
    }

    private function ownedOrder(Request $request, int $orderId): ?Order
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        return $this->orders->find($customer, $orderId);
    }

    private function notFound(): JsonResponse
    {
        return ApiResponse::error(ApiErrorCode::OrderNotFound->defaultMessage(), ApiErrorCode::OrderNotFound, 404);
    }
}
