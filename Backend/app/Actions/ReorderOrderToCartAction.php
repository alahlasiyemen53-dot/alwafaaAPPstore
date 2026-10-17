<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\CustomerCartService;
use Illuminate\Support\Facades\DB;

final class ReorderOrderToCartAction
{
    public function __construct(
        private readonly CustomerCartService $cartService,
    ) {}

    /**
     * @return array{
     *     added: list<array{product_variant_id: int, quantity: int}>,
     *     skipped: list<array{product_variant_id: int|null, reason: string}>,
     *     added_count: int,
     *     skipped_count: int
     * }
     */
    public function execute(Customer $customer, Order $order): array
    {
        $added = [];
        $skipped = [];

        /** @var list<OrderItem> $items */
        $items = $order->items()->get()->all();

        DB::transaction(function () use ($customer, $items, &$added, &$skipped): void {
            foreach ($items as $item) {
                $variantId = $item->product_variant_id !== null
                    ? (int) $item->product_variant_id
                    : null;

                if ($variantId === null || $variantId <= 0) {
                    $skipped[] = [
                        'product_variant_id' => null,
                        'reason' => 'variant_missing',
                    ];
                    continue;
                }

                $variant = ProductVariant::query()->with(['product', 'supplier', 'inventories'])->find($variantId);
                if ($variant === null) {
                    $skipped[] = [
                        'product_variant_id' => $variantId,
                        'reason' => 'variant_not_found',
                    ];
                    continue;
                }

                $availability = $this->cartService->resolveAvailability($variant);
                if (! ($availability['is_available'] ?? false)) {
                    $skipped[] = [
                        'product_variant_id' => $variantId,
                        'reason' => 'unavailable',
                    ];
                    continue;
                }

                $quantity = max(1, min(100, (int) $item->quantity));
                $result = $this->cartService->addItem($customer, $variantId, $quantity);

                if (! ($result['success'] ?? false)) {
                    $skipped[] = [
                        'product_variant_id' => $variantId,
                        'reason' => 'add_failed',
                    ];
                    continue;
                }

                $added[] = [
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ];
            }
        });

        return [
            'added' => $added,
            'skipped' => $skipped,
            'added_count' => count($added),
            'skipped_count' => count($skipped),
        ];
    }
}
