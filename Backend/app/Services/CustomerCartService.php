<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OrderItemData;
use App\Enums\ApiErrorCode;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CustomerCartService
{
    public function __construct(
        private readonly OrderPricingService $orderPricingService,
        private readonly CouponEligibilityService $couponEligibilityService,
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * Get or create the customer's persistent cart.
     */
    public function getOrCreateCart(Customer $customer): Cart
    {
        /** @var Cart $cart */
        $cart = Cart::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['coupon_code' => null],
        );

        return $cart;
    }

    /**
     * Load cart with all necessary relations in a single eager-loading batch.
     */
    public function loadCartWithRelations(Customer $customer): Cart
    {
        $cart = $this->getOrCreateCart($customer);

        $cart->load([
            'items' => fn ($q) => $q->orderBy('id', 'asc'),
            'items.variant.product.media',
            'items.variant.product.brand',
            'items.variant.product.category',
            'items.variant.prices',
            'items.variant.inventories',
            'items.variant.supplier',
            'items.variant.attributeValues.attribute',
            'items.variant.media',
        ]);

        return $cart;
    }

    /**
     * Build the authoritative cart response data.
     *
     * @return array<string, mixed>
     */
    public function getCartDetails(Customer $customer): array
    {
        $cart = $this->loadCartWithRelations($customer);
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        /** @var Collection<int, CartItem> $items */
        $items = $cart->items;

        if ($items->isEmpty()) {
            return [
                'cart_id' => $cart->id,
                'items' => [],
                'items_count' => 0,
                'total_quantity' => 0,
                'currency' => [
                    'id' => $baseCurrency->id,
                    'code' => $baseCurrency->code,
                    'symbol' => $baseCurrency->symbol ?? $baseCurrency->code,
                    'is_base' => true,
                ],
                'pricing' => [
                    'items_subtotal' => 0.0,
                    'item_discounts_total' => 0.0,
                    'coupon_discount' => 0.0,
                    'estimated_total' => 0.0,
                ],
                'coupon' => null,
            ];
        }

        // Prepare items for OrderPricingService and CouponEligibilityService
        $orderItemsData = [];
        $validItems = [];
        $couponLines = [];

        foreach ($items as $cartItem) {
            $variant = $cartItem->variant;
            if ($variant && $variant->status === ProductVariantStatus::Active && $variant->product?->status === ProductStatus::Active) {
                $orderItemsData[] = new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: (float) $cartItem->quantity,
                );
                $validItems[] = $cartItem;

                $basePrice = $variant->prices->firstWhere('currency_id', $baseCurrency->id)?->price ?? 0.0;
                $lineSubtotal = (float) $basePrice * (float) $cartItem->quantity;
                $couponLines[] = [
                    'variant' => $variant,
                    'line_subtotal' => $lineSubtotal,
                    'line_subtotal_after_item' => $lineSubtotal,
                    'item_discount_amount' => 0.0,
                ];
            }
        }

        // Determine coupon validity if coupon_code is set
        $couponInfo = null;
        $activeCouponCode = null;

        if (! empty($cart->coupon_code)) {
            $validation = $this->couponEligibilityService->validate(
                code: $cart->coupon_code,
                linesOrSubtotal: $couponLines,
                customerId: $customer->id,
            );

            if ($validation['is_valid']) {
                $activeCouponCode = $cart->coupon_code;
                $couponInfo = [
                    'code' => $cart->coupon_code,
                    'is_valid' => true,
                    'discount_amount' => (float) $validation['discount_amount'],
                    'message' => 'تم تطبيق الكوبون بنجاح.',
                ];
            } else {
                $couponInfo = [
                    'code' => $cart->coupon_code,
                    'is_valid' => false,
                    'discount_amount' => 0.0,
                    'message' => $validation['error_message'] ?? 'الكوبون المحدد غير صالح.',
                ];
            }
        }

        // Calculate authoritative pricing
        $pricingResult = null;
        if (! empty($orderItemsData)) {
            try {
                $pricingResult = $this->orderPricingService->calculate(
                    currencyId: $baseCurrency->id,
                    itemsData: $orderItemsData,
                    couponCode: $activeCouponCode,
                    customerId: $customer->id,
                );
            } catch (DomainException $e) {
                // Fallback without coupon if coupon caused conflict
                if ($activeCouponCode !== null) {
                    $pricingResult = $this->orderPricingService->calculate(
                        currencyId: $baseCurrency->id,
                        itemsData: $orderItemsData,
                        couponCode: null,
                        customerId: $customer->id,
                    );
                    if ($couponInfo) {
                        $couponInfo['is_valid'] = false;
                        $couponInfo['discount_amount'] = 0.0;
                        $couponInfo['message'] = $e->getMessage();
                    }
                }
            }
        }

        // Key pricing results by product_variant_id for easy lookup
        $pricedItemsByVariant = [];
        if ($pricingResult) {
            foreach ($pricingResult->items as $pItem) {
                $pricedItemsByVariant[$pItem['product_variant_id']] = $pItem;
            }
        }

        $formattedItems = [];
        $totalQuantity = 0;

        foreach ($items as $cartItem) {
            $variant = $cartItem->variant;
            $totalQuantity += $cartItem->quantity;

            $priced = $variant ? ($pricedItemsByVariant[$variant->id] ?? null) : null;

            // Compute Sprint 21.4 availability
            $availability = $this->resolveAvailability($variant);

            $unitPrice = $priced ? (float) $priced['unit_price'] : 0.0;
            $lineSubtotal = $priced ? (float) $priced['line_subtotal'] : 0.0;
            $itemDiscount = $priced ? (float) $priced['item_discount_amount'] : 0.0;
            $lineTotal = $priced ? (float) $priced['line_total'] : 0.0;

            $formattedItems[] = [
                'id' => $cartItem->id,
                'product_variant_id' => $cartItem->product_variant_id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'item_discount' => $itemDiscount,
                'line_total' => $lineTotal,
                'availability' => $availability,
                'product' => $variant?->product ? [
                    'id' => $variant->product->id,
                    'name' => $variant->product->name_ar ?: $variant->product->name_en,
                    'slug' => $variant->product->slug,
                    'brand' => $variant->product->brand?->name_ar ?: $variant->product->brand?->name_en,
                    // A selected variant must not inherit an unrelated catalog image.
                    'primary_image_url' => $variant->getPrimaryImageUrl('small'),
                ] : null,
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'attributes' => $variant->attributeValues->map(fn ($av) => [
                        'attribute_id' => $av->attribute_id,
                        'code' => $av->attribute?->code,
                        'name' => $av->attribute?->name_ar ?: $av->attribute?->name_en,
                        'name_ar' => $av->attribute?->name_ar,
                        'name_en' => $av->attribute?->name_en,
                        'value' => $av->value_ar ?: $av->value_en,
                        'value_ar' => $av->value_ar,
                        'value_en' => $av->value_en,
                    ])->values()->all(),
                ] : null,
            ];
        }

        $itemsSubtotal = $pricingResult ? $pricingResult->originalSubtotal : 0.0;
        $itemDiscountsTotal = $pricingResult ? ($pricingResult->promotionDiscountTotal + $pricingResult->bundleDiscountTotal) : 0.0;
        $couponDiscount = $pricingResult ? $pricingResult->couponDiscountTotal : 0.0;
        $estimatedTotal = $pricingResult ? $pricingResult->finalTotal : 0.0;

        return [
            'cart_id' => $cart->id,
            'items' => $formattedItems,
            'items_count' => count($formattedItems),
            'total_quantity' => $totalQuantity,
            'currency' => [
                'id' => $baseCurrency->id,
                'code' => $baseCurrency->code,
                'symbol' => $baseCurrency->symbol ?? $baseCurrency->code,
                'is_base' => true,
            ],
            'pricing' => [
                'items_subtotal' => $itemsSubtotal,
                'item_discounts_total' => $itemDiscountsTotal,
                'coupon_discount' => $couponDiscount,
                'estimated_total' => $estimatedTotal,
            ],
            'coupon' => $couponInfo,
        ];
    }

    /**
     * Add item to cart with bounded quantity (1..100) and idempotent increment.
     *
     * @return array{success: bool, error_code?: ApiErrorCode, message?: string, item?: CartItem}
     */
    public function addItem(Customer $customer, int $variantId, int $quantity = 1): array
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if (! $variant) {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::ProductVariantNotFound,
                'message' => 'نوع المنتج المحدد غير موجود.',
            ];
        }

        if ($variant->status !== ProductVariantStatus::Active || ($variant->product && $variant->product->status !== ProductStatus::Active)) {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::ProductVariantUnavailable,
                'message' => 'نوع المنتج المحدد غير متوفر حالياً.',
            ];
        }

        if ($quantity < 1 || $quantity > 100) {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::InvalidQuantity,
                'message' => 'الكمية يجب أن تكون بين 1 و 100.',
            ];
        }

        return DB::transaction(function () use ($customer, $variantId, $quantity) {
            $cart = $this->getOrCreateCart($customer);

            /** @var CartItem|null $existingItem */
            $existingItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($existingItem) {
                $newQty = min(100, $existingItem->quantity + $quantity);
                $existingItem->update(['quantity' => $newQty]);
                $item = $existingItem;
            } else {
                $item = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variantId,
                    'quantity' => min(100, $quantity),
                ]);
            }

            return [
                'success' => true,
                'item' => $item,
            ];
        });
    }

    /**
     * Update item quantity (1..100).
     *
     * @return array{success: bool, error_code?: ApiErrorCode, message?: string, item?: CartItem}
     */
    public function updateItem(Customer $customer, int $itemId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 100) {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::InvalidQuantity,
                'message' => 'الكمية يجب أن تكون بين 1 و 100.',
            ];
        }

        return DB::transaction(function () use ($customer, $itemId, $quantity) {
            $cart = $this->getOrCreateCart($customer);

            /** @var CartItem|null $cartItem */
            $cartItem = CartItem::query()
                ->where('id', $itemId)
                ->where('cart_id', $cart->id)
                ->lockForUpdate()
                ->first();

            if (! $cartItem) {
                return [
                    'success' => false,
                    'error_code' => ApiErrorCode::CartItemNotFound,
                    'message' => 'عنصر السلة غير موجود.',
                ];
            }

            $cartItem->update(['quantity' => $quantity]);

            return [
                'success' => true,
                'item' => $cartItem,
            ];
        });
    }

    /**
     * Remove an item from the cart.
     *
     * @return array{success: bool, error_code?: ApiErrorCode, message?: string}
     */
    public function removeItem(Customer $customer, int $itemId): array
    {
        return DB::transaction(function () use ($customer, $itemId) {
            $cart = $this->getOrCreateCart($customer);

            /** @var CartItem|null $cartItem */
            $cartItem = CartItem::query()
                ->where('id', $itemId)
                ->where('cart_id', $cart->id)
                ->lockForUpdate()
                ->first();

            if (! $cartItem) {
                return [
                    'success' => false,
                    'error_code' => ApiErrorCode::CartItemNotFound,
                    'message' => 'عنصر السلة غير موجود.',
                ];
            }

            $cartItem->delete();

            return ['success' => true];
        });
    }

    /**
     * Clear all items and coupon from the customer's cart.
     */
    public function clearCart(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $cart = $this->getOrCreateCart($customer);
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);
        });
    }

    /**
     * Apply coupon to the customer's cart.
     *
     * @return array{success: bool, error_code?: ApiErrorCode, message?: string, discount_amount?: float}
     */
    public function applyCoupon(Customer $customer, string $code): array
    {
        $canonicalCode = Coupon::normalizeCode($code);
        if ($canonicalCode === '') {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::CouponInvalid,
                'message' => 'يرجى إدخال كود الكوبون.',
            ];
        }

        $cart = $this->loadCartWithRelations($customer);

        if ($cart->items->isEmpty()) {
            return [
                'success' => false,
                'error_code' => ApiErrorCode::CouponNotEligible,
                'message' => 'لا يمكن تطبيق الكوبون على سلة فارغة.',
            ];
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $couponLines = [];
        foreach ($cart->items as $cartItem) {
            $variant = $cartItem->variant;
            if ($variant && $variant->status === ProductVariantStatus::Active) {
                $basePrice = $variant->prices->firstWhere('currency_id', $baseCurrency->id)?->price ?? 0.0;
                $lineSubtotal = (float) $basePrice * (float) $cartItem->quantity;
                $couponLines[] = [
                    'variant' => $variant,
                    'line_subtotal' => $lineSubtotal,
                    'line_subtotal_after_item' => $lineSubtotal,
                    'item_discount_amount' => 0.0,
                ];
            }
        }

        $validation = $this->couponEligibilityService->validate(
            code: $canonicalCode,
            linesOrSubtotal: $couponLines,
            customerId: $customer->id,
        );

        if (! $validation['is_valid']) {
            $coupon = $validation['coupon'];
            $errorCode = ApiErrorCode::CouponInvalid;

            if ($coupon && $coupon->ends_at && now()->isAfter($coupon->ends_at)) {
                $errorCode = ApiErrorCode::CouponExpired;
            } elseif ($coupon && $coupon->is_active) {
                $errorCode = ApiErrorCode::CouponNotEligible;
            }

            return [
                'success' => false,
                'error_code' => $errorCode,
                'message' => $validation['error_message'] ?? 'الكوبون المحدد غير صالح.',
            ];
        }

        // Coupon is valid, attach to cart
        $cart->update(['coupon_code' => $canonicalCode]);

        return [
            'success' => true,
            'message' => 'تم تطبيق كود الكوبون بنجاح.',
            'discount_amount' => (float) $validation['discount_amount'],
        ];
    }

    /**
     * Remove applied coupon from the customer's cart.
     */
    public function removeCoupon(Customer $customer): void
    {
        $cart = $this->getOrCreateCart($customer);
        $cart->update(['coupon_code' => null]);
    }

    /**
     * Resolve variant availability according to Sprint 21.4 rules.
     *
     * @return array{is_available: bool, status: 'in_stock'|'on_demand'|'out_of_stock', available_quantity: int}
     */
    public function resolveAvailability(?ProductVariant $variant): array
    {
        if (! $variant || $variant->status !== ProductVariantStatus::Active) {
            return [
                'is_available' => false,
                'status' => 'out_of_stock',
                'available_quantity' => 0,
            ];
        }

        $mode = $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked;

        if ($mode === ProductVariantFulfillmentMode::Stocked) {
            $totalStock = 0.0;
            if ($variant->relationLoaded('inventories')) {
                foreach ($variant->inventories as $inv) {
                    $avail = max(0.0, (float) $inv->quantity - (float) $inv->reserved_quantity);
                    $totalStock += $avail;
                }
            }

            $isAvailable = $totalStock > 0.0;

            return [
                'is_available' => $isAvailable,
                'status' => $isAvailable ? 'in_stock' : 'out_of_stock',
                'available_quantity' => (int) floor($totalStock),
            ];
        }

        // OnDemand mode
        $supplier = $variant->relationLoaded('supplier') ? $variant->supplier : $variant->supplier()->first();
        $isAvailable = $supplier !== null && (bool) $supplier->is_active;

        return [
            'is_available' => $isAvailable,
            'status' => $isAvailable ? 'on_demand' : 'out_of_stock',
            'available_quantity' => $isAvailable ? 999 : 0,
        ];
    }
}
