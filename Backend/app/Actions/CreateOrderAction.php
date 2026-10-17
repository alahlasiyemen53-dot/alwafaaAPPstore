<?php

namespace App\Actions;

use App\DTOs\CreateOrderData;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Services\Delivery\GovernorateDeliveryAvailability;
use App\Models\OrderItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateOrderAction
{
    public function __construct(
        private readonly GenerateOrderNumberAction $generateOrderNumber,
        private readonly CalculateOrderTotalsAction $calculateOrderTotals,
        private readonly CreateOrderAddressSnapshotAction $createAddressSnapshot,
        private readonly AuditLogger $auditLogger,
        private readonly \App\Services\GiftWrappingService $giftWrappingService,
        private readonly GovernorateDeliveryAvailability $governorateDeliveryAvailability,
    ) {}

    public function execute(CreateOrderData $data, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($data, $userId): Order {
            $customer = Customer::query()->findOrFail($data->customerId);
            if (! $customer->is_active) {
                throw new DomainException('العميل المحدد معطل ولا يمكن إنشاء طلب له.');
            }

            $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
            if (! $warehouse->is_active) {
                throw new DomainException('المستودع المحدد معطل ولا يمكن تعيينه للطلب.');
            }

            if (empty($data->items)) {
                throw new DomainException('يجب إضافة بند واحد على الأقل للطلب.');
            }

            $address = CustomerAddress::query()
                ->with('governorate')
                ->findOrFail($data->customerAddressId);

            if ($address->customer_id !== $customer->id) {
                throw new DomainException('عنوان التوصيل المحدد لا ينتمي للعميل صاحب الطلب.');
            }

            $governorate = $address->governorate;
            if ($governorate === null || ! $governorate->is_active) {
                throw new DomainException('محافظة عنوان التوصيل المحددة غير صالحة أو غير نشطة.');
            }

            $methodCode = $data->shippingMethod !== null && trim($data->shippingMethod) !== ''
                ? trim($data->shippingMethod)
                : 'standard';

            $assignment = $this->governorateDeliveryAvailability->resolve($governorate->id, $methodCode);
            if ($assignment === null) {
                throw new DomainException('طريقة التوصيل المحددة غير متاحة للمحافظة المحددة.');
            }

            $deliveryFeeBase = (float) $assignment->delivery_fee;

            $calculated = $this->calculateOrderTotals->execute(
                currencyId: $data->currencyId,
                itemsData: $data->items,
                couponCode: $data->couponCode,
                customerId: $customer->id,
                deliveryFeeBase: $deliveryFeeBase,
            );

            $orderNumber = $this->generateOrderNumber->execute();
            $giftMessageSnapshot = $this->giftWrappingService->validateAndCleanMessage(
                $data->giftMessage,
                $data->giftWrappingRequested
            );

            $deliveryMethod = $assignment->deliveryMethod;

            $order = Order::query()->create([
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'currency_id' => $data->currencyId,
                'coupon_id' => $calculated['coupon_id'] ?? null,
                'coupon_code_snapshot' => $calculated['coupon_code_snapshot'] ?? null,
                'coupon_name_snapshot' => $calculated['coupon_name_snapshot'] ?? null,
                'coupon_discount_type_snapshot' => $calculated['coupon_discount_type_snapshot'] ?? null,
                'coupon_discount_value_base_snapshot' => $calculated['coupon_discount_value_base_snapshot'] ?? null,
                'status' => OrderStatus::Draft,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'subtotal' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'delivery_fee' => $calculated['delivery_fee'],
                'base_currency_delivery_fee' => $calculated['base_currency_delivery_fee'],
                'promotion_discount_total' => $calculated['promotion_discount_total'],
                'coupon_discount_total' => $calculated['coupon_discount_total'],
                'bundle_discount_total' => $calculated['bundle_discount_total'],
                'total' => $calculated['total'],
                'exchange_rate_snapshot' => $calculated['exchange_rate_snapshot'],
                'base_currency_total' => $calculated['base_currency_total'],
                'notes' => $data->notes,
                'gift_wrapping_requested' => $data->giftWrappingRequested,
                'gift_message_snapshot' => $giftMessageSnapshot,
                'shipping_method_code' => $deliveryMethod?->code ?? $methodCode,
                'shipping_method_name_ar' => $deliveryMethod?->name_ar ?? 'توصيل قياسي',
                'shipping_method_name_en' => $deliveryMethod?->name_en ?? 'Standard Delivery',
                'created_by' => $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null),
                'updated_by' => $userId ?? (auth()->user() instanceof \App\Models\User ? auth()->id() : null),
            ]);

            foreach ($calculated['items'] as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::query()->create($itemData);
            }

            $this->createAddressSnapshot->execute($order, $data->customerAddressId);

            $this->auditLogger->record('admin.order.created', $order, metadata: [
                'order_number' => $order->order_number,
                'customer_id' => $customer->id,
                'total' => $order->total,
                'items_count' => count($calculated['items']),
            ]);

            return $order->load(['customer', 'warehouse', 'currency', 'items.productVariant', 'deliveryAddress']);
        });
    }
}
