<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\ProductReviewStatus;
use App\Models\DeliveryRating;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SubmitOrderRatingsAction
{
    /**
     * @param  list<array{product_id?: int, order_item_id?: int, rating: int, body?: ?string}>  $productRatings
     * @return array{product_reviews: int, driver_rating: bool}
     */
    public function execute(
        Order $order,
        int $customerId,
        array $productRatings = [],
        ?int $driverRating = null,
        ?string $driverComment = null,
    ): array {
        return DB::transaction(function () use ($order, $customerId, $productRatings, $driverRating, $driverComment): array {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with(['items.productVariant', 'deliveries'])
                ->findOrFail($order->id);

            if ((int) $lockedOrder->customer_id !== $customerId) {
                throw new DomainException('لا يمكن تقييم طلب لا يخصك.');
            }

            if ($lockedOrder->status !== OrderStatus::Delivered) {
                throw new DomainException('يمكن التقييم فقط بعد اكتمال الطلب.');
            }

            $createdReviews = 0;
            foreach ($productRatings as $row) {
                $rating = (int) ($row['rating'] ?? 0);
                if ($rating < 1 || $rating > 5) {
                    continue;
                }

                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0 && ! empty($row['order_item_id'])) {
                    $item = $lockedOrder->items->firstWhere('id', (int) $row['order_item_id']);
                    $productId = (int) ($item?->productVariant?->product_id ?? 0);
                }

                if ($productId <= 0) {
                    continue;
                }

                $belongs = $lockedOrder->items->contains(
                    fn (OrderItem $item): bool => (int) ($item->productVariant?->product_id ?? 0) === $productId
                );
                if (! $belongs) {
                    continue;
                }

                ProductReview::query()->updateOrCreate(
                    [
                        'customer_id' => $customerId,
                        'product_id' => $productId,
                    ],
                    [
                        'order_id' => $lockedOrder->id,
                        'rating' => $rating,
                        'body' => isset($row['body']) ? trim((string) $row['body']) : null,
                        'status' => ProductReviewStatus::Approved,
                        'is_verified_purchase' => true,
                    ]
                );
                $createdReviews++;
            }

            $driverRated = false;
            if ($driverRating !== null) {
                if ($driverRating < 1 || $driverRating > 5) {
                    throw new DomainException('تقييم المندوب يجب أن يكون بين 1 و 5.');
                }

                $delivery = $lockedOrder->deliveries->sortByDesc('id')->first();
                DeliveryRating::query()->updateOrCreate(
                    ['order_id' => $lockedOrder->id],
                    [
                        'delivery_id' => $delivery?->id,
                        'driver_id' => $delivery?->driver_id,
                        'customer_id' => $customerId,
                        'rating' => $driverRating,
                        'comment' => $driverComment !== null ? trim($driverComment) : null,
                    ]
                );
                $driverRated = true;
            }

            return [
                'product_reviews' => $createdReviews,
                'driver_rating' => $driverRated,
            ];
        });
    }
}
