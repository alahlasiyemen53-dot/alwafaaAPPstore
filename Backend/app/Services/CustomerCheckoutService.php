<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CalculateOrderTotalsAction;
use App\Actions\CreateOrderAction;
use App\Actions\Payments\PostOnlineWalletPaymentAction;
use App\Data\Payments\PaymentCaptureCommand;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPaymentMethod;
use App\Models\GovernorateDeliveryMethod;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Payments\PaymentProviderRegistry;
use App\Services\Delivery\GovernorateDeliveryAvailability;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerCheckoutService
{
    public function __construct(
        private readonly CustomerCartService $cartService,
        private readonly CalculateOrderTotalsAction $calculateOrderTotals,
        private readonly CreateOrderAction $createOrderAction,
        private readonly GiftWrappingService $giftWrappingService,
        private readonly CurrencyConverter $currencyConverter,
        private readonly GovernorateDeliveryAvailability $governorateDeliveryAvailability,
        private readonly PaymentProviderRegistry $paymentProviders,
        private readonly PostOnlineWalletPaymentAction $postOnlineWalletPayment,
    ) {}

    /**
     * Compute authoritative informational preview of checkout.
     * Preview does NOT lock inventory or alter cart state (preview ≠ reservation).
     *
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function preview(Customer $customer, array $intent): array
    {
        if (! $customer->is_active) {
            throw new DomainException('حساب العميل معطل حالياً ولا يمكنه إجراء عمليات شراء.');
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $cart = $this->cartService->loadCartWithRelations($customer);
        $warnings = [];
        $canCheckout = true;

        if ($cart->items->isEmpty()) {
            $shippingMethods = $this->getShippingMethods();

            return [
                'can_checkout' => false,
                'warnings' => ['سلة المشتريات فارغة. يرجى إضافة منتجات للمتابعة.'],
                'delivery_address' => null,
                'delivery_fee' => 0.0,
                'shipping_methods' => $shippingMethods,
                'payment_methods' => $this->getPaymentMethods(),
                'gift_wrapping' => array_merge($this->giftWrappingService->getSettings(), [
                    'requested' => false,
                    'fee' => 0.0,
                    'message' => null,
                ]),
                'selected_shipping_method' => $shippingMethods[0]['code'] ?? 'standard',
                'items' => [],
                'items_count' => 0,
                'total_quantity' => 0,
                'pricing' => [
                    'subtotal' => 0.0,
                    'items_discount' => 0.0,
                    'coupon_discount' => 0.0,
                    'discount_amount' => 0.0,
                    'delivery_fee' => 0.0,
                    'gift_wrapping_fee' => 0.0,
                    'total' => 0.0,
                ],
                'currency' => [
                    'id' => $baseCurrency->id,
                    'code' => $baseCurrency->code,
                    'symbol' => $baseCurrency->symbol ?? $baseCurrency->code,
                ],
            ];
        }

        $checkoutItems = $this->filterCartItemsForCheckout($cart->items, $intent);

        // 1. Resolve Delivery Address
        $address = null;
        if (! empty($intent['customer_address_id'])) {
            $address = CustomerAddress::query()
                ->with(['country', 'governorate'])
                ->where('id', $intent['customer_address_id'])
                ->first();

            if ($address === null || $address->customer_id !== $customer->id) {
                throw new DomainException('عنوان التوصيل المحدد غير موجود أو لا ينتمي لهذا الحساب.');
            }
        } else {
            /** @var CustomerAddress|null $address */
            $address = CustomerAddress::query()
                ->with(['country', 'governorate'])
                ->where('customer_id', $customer->id)
                ->where('is_default', true)
                ->first()
                ?? CustomerAddress::query()
                    ->with(['country', 'governorate'])
                    ->where('customer_id', $customer->id)
                    ->latest()
                    ->first();
        }

        $deliveryFee = 0.0;
        $shippingMethodCode = (string) ($intent['shipping_method'] ?? 'standard');
        $governorateIdForShipping = null;

        if ($address !== null) {
            $governorate = $address->governorate;
            if ($governorate === null || ! $governorate->is_active) {
                $warnings[] = 'خدمة التوصيل غير متوفرة حالياً في المحافظة المحددة للعنوان.';
                $canCheckout = false;
            } else {
                $governorateIdForShipping = $governorate->id;
                $assignment = $this->governorateDeliveryAvailability->resolve($governorate->id, $shippingMethodCode);

                if ($assignment === null) {
                    $available = $this->governorateDeliveryAvailability
                        ->listAvailableForGovernorate($governorate->id);
                    $fallback = $available->first();

                    // Address changes reset to "standard". If that method is not
                    // offered for the new governorate, adopt the first available
                    // method so delivery fee updates instead of staying at 0.
                    // Explicitly requested unavailable methods still fail checkout.
                    if ($fallback !== null && ($shippingMethodCode === '' || $shippingMethodCode === 'standard')) {
                        $assignment = $fallback;
                    } elseif ($fallback === null) {
                        $warnings[] = 'لا توجد طرق توصيل متاحة حالياً لهذه المحافظة.';
                        $canCheckout = false;
                    } else {
                        $warnings[] = 'طريقة التوصيل المحددة غير متاحة حالياً.';
                        $canCheckout = false;
                    }
                }

                if ($assignment !== null) {
                    $shippingMethodCode = (string) ($assignment->deliveryMethod?->code ?? $shippingMethodCode);
                    $deliveryFee = (float) $assignment->delivery_fee;
                }
            }
        } else {
            $warnings[] = 'يرجى اختيار عنوان التوصيل لحساب رسوم التوصيل وإتمام الطلب.';
            $canCheckout = false;
        }

        // 2. Resolve Coupon
        $couponCode = ! empty($intent['coupon_code'])
            ? (string) $intent['coupon_code']
            : $cart->coupon_code;

        // 3. Resolve Gift Wrapping (respect backend feature flag)
        $giftSettings = $this->giftWrappingService->getSettings();
        $giftWrappingRequested = $giftSettings['enabled']
            && (bool) ($intent['gift_wrapping_requested'] ?? false);
        $giftMessage = null;
        if ($giftWrappingRequested) {
            $giftMessage = $this->giftWrappingService->validateAndCleanMessage(
                $intent['gift_message'] ?? null,
                true
            );
        }

        // 4. Build OrderItemData list for pricing engine (selected lines only)
        $itemsData = [];
        foreach ($checkoutItems as $cartItem) {
            $itemsData[] = new OrderItemData(
                productVariantId: $cartItem->product_variant_id,
                quantity: (float) $cartItem->quantity,
                unitId: $cartItem->variant?->unit_id,
            );
        }

        // 5. Calculate Order Totals
        $calculated = $this->calculateOrderTotals->execute(
            currencyId: $baseCurrency->id,
            itemsData: $itemsData,
            couponCode: $couponCode,
            customerId: $customer->id,
            now: now(),
            lockForUpdate: false,
            deliveryFeeBase: $deliveryFee,
        );

        // 6. Inspect stock availability and fulfillment mode per item
        $destinationWarehouse = $this->resolveWarehouse($address);
        $previewItems = [];
        $totalQuantity = 0;

        $variantIds = array_column($calculated['items'], 'product_variant_id');
        $variantsById = ProductVariant::query()
            ->with(['inventories', 'product.media', 'supplier', 'media'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        foreach ($calculated['items'] as $calcItem) {
            /** @var ProductVariant|null $variant */
            $variant = $variantsById->get($calcItem['product_variant_id']);

            $isAvailable = true;
            $stockWarning = null;

            if ($variant === null || $variant->status !== \App\Enums\ProductVariantStatus::Active || ($variant->product?->status !== \App\Enums\ProductStatus::Active)) {
                $isAvailable = false;
                $stockWarning = 'المنتج غير متوفر حالياً.';
                $warnings[] = "المنتج ({$calcItem['product_name_snapshot']}) غير متاح حالياً.";
                $canCheckout = false;
            } elseif ($variant->isStocked()) {
                $inventory = $destinationWarehouse !== null
                    ? $variant->inventories->firstWhere('warehouse_id', $destinationWarehouse->id)
                    : null;

                $currentQty = $inventory ? (float) $inventory->quantity : 0.0;
                $reservedQty = $inventory ? (float) $inventory->reserved_quantity : 0.0;
                $availableQty = max(0.0, $currentQty - $reservedQty);

                if ($calcItem['quantity'] > $availableQty) {
                    $isAvailable = false;
                    $stockWarning = "الكمية المتوفرة في المخزون ({$availableQty}) أقل من الكمية المطلوبة ({$calcItem['quantity']}).";
                    $warnings[] = "الكمية المتاحة من ({$calcItem['variant_name_snapshot']}) غير كافية.";
                    $canCheckout = false;
                }
            } elseif ($variant->isOnDemand()) {
                if ($variant->supplier === null || ! $variant->supplier->is_active) {
                    $isAvailable = false;
                    $stockWarning = 'مورد المنتج غير متاح حالياً للتوفير عند الطلب.';
                    $warnings[] = "مورد المنتج ({$calcItem['product_name_snapshot']}) غير متاح.";
                    $canCheckout = false;
                }
            }

            $previewItems[] = [
                'product_variant_id' => $calcItem['product_variant_id'],
                'product_name' => $calcItem['product_name_snapshot'],
                'variant_name' => $calcItem['variant_name_snapshot'],
                'sku' => $calcItem['sku_snapshot'],
                'fulfillment_mode' => $variant?->fulfillment_mode?->value ?? 'stocked',
                'quantity' => (float) $calcItem['quantity'],
                'unit_price' => (float) $calcItem['unit_price'],
                'original_unit_price' => (float) ($calcItem['original_unit_price'] ?? $calcItem['unit_price']),
                'line_subtotal' => (float) $calcItem['line_subtotal'],
                'discount_amount' => (float) $calcItem['discount_amount'],
                'line_total' => (float) $calcItem['line_total'],
                'is_available' => $isAvailable,
                'warning' => $stockWarning,
                'primary_image_url' => $variant?->getPrimaryImageUrl('small'),
            ];

            $totalQuantity += (int) $calcItem['quantity'];
        }

        return [
            'can_checkout' => $canCheckout,
            'warnings' => array_values(array_unique($warnings)),
            'delivery_address' => $address ? [
                'id' => $address->id,
                'label' => $address->label,
                'contact_name' => $address->contact_name,
                'contact_phone' => $address->contact_phone,
                'country' => $address->country?->name_ar,
                'governorate' => $address->governorate?->name_ar,
                'neighborhood' => $address->neighborhood,
                'street' => $address->street,
                'building' => $address->building,
                'floor' => $address->floor,
                'apartment' => $address->apartment,
                'landmark' => $address->landmark,
                'notes' => $address->notes,
                'formatted_address' => $address->formatted_address,
                'delivery_fee' => $deliveryFee,
            ] : null,
            'shipping_methods' => $this->getShippingMethods($governorateIdForShipping),
            'payment_methods' => $this->getPaymentMethods(),
            'gift_wrapping' => [
                'enabled' => $giftSettings['enabled'],
                'message_enabled' => $giftSettings['message_enabled'],
                'max_length' => $giftSettings['max_length'],
                'requested' => $giftWrappingRequested,
                'is_free' => $giftSettings['is_free'],
                'fee' => 0.0,
                'message' => $giftMessage,
            ],
            'selected_shipping_method' => $shippingMethodCode,
            'items' => $previewItems,
            'items_count' => count($previewItems),
            'total_quantity' => $totalQuantity,
            'pricing' => [
                'subtotal' => (float) $calculated['subtotal'],
                'items_discount' => (float) ($calculated['promotion_discount_total'] + $calculated['bundle_discount_total']),
                'coupon_discount' => (float) $calculated['coupon_discount_total'],
                'discount_amount' => (float) $calculated['discount_amount'],
                'delivery_fee' => (float) $calculated['delivery_fee'],
                'gift_wrapping_fee' => 0.0,
                'total' => (float) $calculated['total'],
                'coupon_code' => $calculated['coupon_code_snapshot'],
            ],
            'currency' => [
                'id' => $baseCurrency->id,
                'code' => $baseCurrency->code,
                'symbol' => $baseCurrency->symbol ?? $baseCurrency->code,
            ],
        ];
    }

    /**
     * Transactional Order Creation.
     * Enforces authoritative re-read, STOCKED locking/reservation, ON_DEMAND sourcing,
     * coupon consumption, address snapshot, cart clearance, and async FCM notification dispatch.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(Customer $customer, array $data): Order
    {
        $step1Payment = $this->captureStep1PaymentIfNeeded($customer, $data);

        return DB::transaction(function () use ($customer, $data, $step1Payment): Order {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if (! $lockedCustomer->is_active) {
                throw new DomainException('حساب العميل معطل حالياً ولا يمكنه إنشاء طلبات.');
            }

            /** @var Cart $cart */
            $cart = Cart::query()->lockForUpdate()->firstOrCreate(
                ['customer_id' => $lockedCustomer->id],
                ['coupon_code' => null],
            );

            $cartItems = $this->filterCartItemsForCheckout(
                CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->with(['variant.supplier', 'variant.inventories'])
                    ->get(),
                $data,
            );

            if ($cartItems->isEmpty()) {
                throw new DomainException('سلة المشتريات فارغة ولا يمكن إتمام الطلب.');
            }

            /** @var CustomerAddress|null $address */
            $address = CustomerAddress::query()
                ->with('governorate')
                ->where('id', $data['customer_address_id'])
                ->first();

            if ($address === null || $address->customer_id !== $lockedCustomer->id) {
                throw new DomainException('عنوان التوصيل المحدد غير موجود أو لا ينتمي لهذا الحساب.');
            }

            $governorate = $address->governorate;
            if ($governorate === null || ! $governorate->is_active) {
                throw new DomainException('خدمة التوصيل غير متوفرة في المحافظة المحددة.');
            }

            $paymentMethodCode = (string) ($data['payment_method'] ?? 'cod');
            $paymentMethod = CustomerPaymentMethod::query()
                ->where('code', $paymentMethodCode)
                ->where('is_active', true)
                ->first();

            $alreadyCaptured = (bool) ($data['_payment_already_captured'] ?? false);
            $providerCode = $paymentMethod?->provider_code;

            if ($paymentMethod === null) {
                if ($paymentMethodCode !== 'cod') {
                    throw new DomainException('طريقة الدفع المحددة غير متاحة.');
                }
                $providerCode = PaymentProviderCode::Cod;
            }

            if ($providerCode === PaymentProviderCode::Floosak && ! $alreadyCaptured) {
                throw new DomainException('دفع فلوسك يتطلب بدء الفاتورة ثم تأكيد رمز التحقق.');
            }

            if ($providerCode === PaymentProviderCode::Jaib && ! $alreadyCaptured && $step1Payment === null) {
                throw new DomainException('تعذر تنفيذ دفع جيب قبل إنشاء الطلب.');
            }

            $shippingMethodCode = (string) ($data['shipping_method'] ?? 'standard');
            $assignment = $this->governorateDeliveryAvailability->resolve($governorate->id, $shippingMethodCode);
            if ($assignment === null) {
                throw new DomainException('طريقة التوصيل المحددة غير متاحة للمحافظة المحددة.');
            }
            $deliveryMethod = $assignment->deliveryMethod;
            if ($deliveryMethod === null) {
                throw new DomainException('طريقة التوصيل المحددة غير مدعومة.');
            }

            $giftSettings = $this->giftWrappingService->getSettings();
            $giftWrappingRequested = (bool) ($data['gift_wrapping_requested'] ?? false);
            if ($giftWrappingRequested && ! $giftSettings['enabled']) {
                throw new DomainException('خدمة تغليف الهدايا غير مفعّلة حالياً.');
            }
            if (! $giftSettings['enabled']) {
                $giftWrappingRequested = false;
            }

            $warehouse = $this->resolveWarehouse($address);
            if ($warehouse === null || ! $warehouse->is_active) {
                throw new DomainException('لا يوجد مستودع نشط متاح لخدمة هذا الطلب.');
            }

            $baseCurrency = $this->currencyConverter->getBaseCurrency();

            $itemsData = $cartItems->map(fn (CartItem $item) => new OrderItemData(
                productVariantId: $item->product_variant_id,
                quantity: (float) $item->quantity,
                unitId: $item->variant?->unit_id,
            ))->all();

            $couponCode = ! empty($data['coupon_code'])
                ? (string) $data['coupon_code']
                : $cart->coupon_code;

            $createOrderData = new CreateOrderData(
                customerId: $lockedCustomer->id,
                customerAddressId: $address->id,
                warehouseId: $warehouse->id,
                currencyId: $baseCurrency->id,
                items: $itemsData,
                notes: isset($data['notes']) ? (string) $data['notes'] : null,
                couponCode: $couponCode,
                giftWrappingRequested: $giftWrappingRequested,
                giftMessage: $data['gift_message'] ?? null,
                deliveryFeeBase: (float) $assignment->delivery_fee,
                shippingMethod: (string) $deliveryMethod->code,
            );

            $paymentStatus = OrderPaymentStatus::Unpaid;
            $providerReference = null;
            $providerRequestId = null;

            if ($alreadyCaptured) {
                $paymentStatus = OrderPaymentStatus::tryFrom((string) ($data['_payment_status'] ?? ''))
                    ?? OrderPaymentStatus::Paid;
                $providerReference = isset($data['_payment_provider_reference'])
                    ? (string) $data['_payment_provider_reference']
                    : null;
                $providerRequestId = isset($data['_payment_provider_request_id'])
                    ? (string) $data['_payment_provider_request_id']
                    : null;
            } elseif ($step1Payment !== null) {
                $paymentStatus = OrderPaymentStatus::Paid;
                $providerReference = $step1Payment['provider_reference'];
                $providerRequestId = $step1Payment['provider_request_id'];
            }

            // Customer checkout leaves the order under review (draft).
            // Customer service confirms later via ConfirmOrderAction.
            $draftOrder = $this->createOrderAction->execute($createOrderData, null);

            $draftOrder->update([
                'payment_method_code' => $paymentMethodCode,
                'payment_status' => $paymentStatus,
                'payment_provider_reference' => $providerReference,
                'payment_provider_request_id' => $providerRequestId,
            ]);

            if ($paymentStatus === OrderPaymentStatus::Paid) {
                $this->postOnlineWalletPayment->execute($draftOrder->fresh());
            }

            $orderedItemIds = $cartItems->pluck('id')->filter()->all();
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereIn('id', $orderedItemIds)
                ->delete();

            if (! CartItem::query()->where('cart_id', $cart->id)->exists()) {
                $cart->update(['coupon_code' => null]);
            }

            return $draftOrder->fresh(['customer', 'warehouse', 'currency', 'items.productVariant', 'deliveryAddress']);
        });
    }

    /**
     * Restrict checkout to explicitly selected cart lines when cart_item_ids is provided.
     * Omitting the field keeps legacy full-cart checkout behavior.
     *
     * @param  \Illuminate\Support\Collection<int, CartItem>|\Illuminate\Database\Eloquent\Collection<int, CartItem>  $items
     * @param  array<string, mixed>  $intent
     * @return \Illuminate\Support\Collection<int, CartItem>
     */
    private function filterCartItemsForCheckout($items, array $intent)
    {
        $rawIds = $intent['cart_item_ids'] ?? null;
        if (! is_array($rawIds) || $rawIds === []) {
            return $items->values();
        }

        $requestedIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $rawIds,
        )));

        $filtered = $items->whereIn('id', $requestedIds)->values();

        if ($filtered->isEmpty()) {
            throw new DomainException('يرجى اختيار منتج واحد على الأقل لإتمام الشراء.');
        }

        if ($filtered->count() !== count($requestedIds)) {
            throw new DomainException('بعض المنتجات المحددة لم تعد موجودة في السلة. حدّث السلة وحاول مجدداً.');
        }

        return $filtered;
    }

    /**
     * Capture Step1 (Jaib) payment outside the order DB transaction.
     *
     * @param  array<string, mixed>  $data
     * @return array{provider_reference: string, provider_request_id: string}|null
     */
    private function captureStep1PaymentIfNeeded(Customer $customer, array $data): ?array
    {
        if ((bool) ($data['_payment_already_captured'] ?? false)) {
            return null;
        }

        $paymentMethodCode = (string) ($data['payment_method'] ?? 'cod');
        $paymentMethod = CustomerPaymentMethod::query()
            ->where('code', $paymentMethodCode)
            ->where('is_active', true)
            ->first();

        if ($paymentMethod === null || $paymentMethod->provider_code !== PaymentProviderCode::Jaib) {
            return null;
        }

        $paymentPayload = is_array($data['payment'] ?? null) ? $data['payment'] : [];
        $code = trim((string) ($paymentPayload['code'] ?? ''));
        $mobile = preg_replace('/\D+/', '', (string) ($customer->phone ?? '')) ?? '';
        if ($mobile === '') {
            throw new DomainException('رقم جوال الحساب مطلوب لدفع جيب.');
        }
        if ($code === '') {
            throw new DomainException('رمز الشراء مطلوب لدفع جيب.');
        }

        $preview = $this->preview($customer, [
            'customer_address_id' => $data['customer_address_id'] ?? null,
            'shipping_method' => $data['shipping_method'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null,
            'gift_wrapping_requested' => $data['gift_wrapping_requested'] ?? false,
            'gift_message' => $data['gift_message'] ?? null,
            'payment_method' => $paymentMethodCode,
            'cart_item_ids' => $data['cart_item_ids'] ?? null,
        ]);

        $amount = number_format((float) ($preview['pricing']['total'] ?? 0), 4, '.', '');
        $currencyCode = (string) ($preview['currency']['code'] ?? 'YER');
        $requestId = (string) Str::uuid();

        $capture = $this->paymentProviders->get(PaymentProviderCode::Jaib)->capture(
            new PaymentCaptureCommand(
                requestId: $requestId,
                customerPhone: $mobile,
                paymentCode: $code,
                amount: $amount,
                currencyCode: $currencyCode,
                notes: 'طلب الوفاء استور',
            )
        );

        if (! $capture->success || $capture->providerReference === null) {
            throw new DomainException($capture->message ?? 'فشل الدفع عبر جيب.');
        }

        return [
            'provider_reference' => $capture->providerReference,
            'provider_request_id' => $capture->requestId ?? $requestId,
        ];
    }

    /**
     * Resolve destination warehouse: warehouse in customer's governorate or primary active warehouse.
     */
    public function resolveWarehouse(?CustomerAddress $address): ?Warehouse
    {
        if ($address !== null && $address->governorate_id !== null) {
            $warehouse = Warehouse::query()
                ->where('is_active', true)
                ->where('governorate_id', $address->governorate_id)
                ->orderBy('id')
                ->first();
            if ($warehouse !== null) {
                return $warehouse;
            }
        }

        return Warehouse::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Active customer payment methods from catalog (falls back to COD).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentMethods(): array
    {
        $methods = CustomerPaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($methods->isEmpty()) {
            return [[
                'code' => 'cod',
                'provider_code' => PaymentProviderCode::Cod->value,
                'name_ar' => 'الدفع عند الاستلام (COD)',
                'name_en' => 'Cash on Delivery',
                'description_ar' => 'الدفع نقداً عند استلام طلبك من مندوب التوصيل',
                'description_en' => 'Pay with cash upon delivery by the driver',
                'instructions_ar' => null,
                'instructions_en' => null,
                'channel' => 'offline',
                'flow_type' => PaymentFlowType::None->value,
                'logo_url' => null,
                'enabled' => true,
            ]];
        }

        return $methods->map(static function (CustomerPaymentMethod $method): array {
            return [
                'code' => $method->code,
                'provider_code' => $method->provider_code->value,
                'name_ar' => $method->name_ar,
                'name_en' => $method->name_en,
                'description_ar' => $method->description_ar,
                'description_en' => $method->description_en,
                'instructions_ar' => $method->instructions_ar,
                'instructions_en' => $method->instructions_en,
                'channel' => $method->channel->value,
                'flow_type' => $method->flow_type->value,
                'logo_url' => $method->getLogoUrl('thumb') ?? $method->getLogoUrl(),
                'enabled' => true,
            ];
        })->all();
    }

    /**
     * Active delivery methods. Cost comes from the address governorate when known.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getShippingMethods(?int $governorateId = null): array
    {
        if ($governorateId === null) {
            return [];
        }

        $assignments = $this->governorateDeliveryAvailability->listAvailableForGovernorate($governorateId);
        if ($assignments->isEmpty()) {
            return [];
        }

        return $assignments->map(function (GovernorateDeliveryMethod $assignment): array {
            $method = $assignment->deliveryMethod;

            return [
                'id' => $assignment->id,
                'code' => (string) ($method?->code ?? ''),
                'name_ar' => (string) ($method?->name_ar ?? ''),
                'name_en' => $method?->name_en,
                'cost' => (float) $assignment->delivery_fee,
                'estimated_delivery' => $method?->estimated_delivery_ar
                    ?? $method?->estimated_delivery_en
                    ?? 'خلال 24-48 ساعة',
                'enabled' => true,
            ];
        })->all();
    }
}
