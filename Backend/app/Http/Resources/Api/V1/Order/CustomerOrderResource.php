<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\CustomerPaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'items.productVariant.media',
            'items.productVariant.product.media',
        ]);

        $address = $this->deliveryAddress;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status instanceof OrderStatus ? $this->status->value : (string) $this->status,
            'status_label' => $this->status instanceof OrderStatus ? $this->status->label() : null,
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name_snapshot,
                'phone' => $this->customer_phone_snapshot,
            ],
            'delivery_address' => $address ? [
                'contact_name' => $address->contact_name,
                'contact_phone' => $address->contact_phone,
                'governorate' => $address->governorate_name_snapshot,
                'city' => $address->city_name_snapshot,
                'district' => $address->district_name_snapshot,
                'neighborhood' => $address->neighborhood,
                'street' => $address->street,
                'building' => $address->building,
                'floor' => $address->floor,
                'apartment' => $address->apartment,
                'landmark' => $address->landmark,
                'notes' => $address->notes,
                'formatted_address' => $address->formatted_address,
            ] : null,
            'payment_method' => CustomerPaymentMethod::presentationForCode($this->payment_method_code),
            'payment_status' => $this->payment_status instanceof OrderPaymentStatus
                ? $this->payment_status->value
                : ($this->payment_status ?? OrderPaymentStatus::Unpaid->value),
            'payment_provider_reference' => $this->payment_provider_reference,
            'shipping_method' => [
                'code' => $this->shipping_method_code ?? 'standard',
                'name_ar' => $this->shipping_method_name_ar ?? 'توصيل قياسي',
                'name_en' => $this->shipping_method_name_en ?? 'Standard Delivery',
            ],
            'gift_wrapping' => [
                'requested' => (bool) $this->gift_wrapping_requested,
                'message' => $this->gift_message_snapshot,
                'is_free' => true,
                'fee' => 0.0,
            ],
            'items' => $this->items->map(function (OrderItem $item): array {
                return [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name_snapshot,
                    'variant_name' => $item->variant_name_snapshot,
                    'sku' => $item->sku_snapshot,
                    'fulfillment_mode' => $item->fulfillment_mode_snapshot?->value ?? ($item->isStocked() ? 'stocked' : 'on_demand'),
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'original_unit_price' => (float) ($item->original_unit_price ?? $item->unit_price),
                    'line_subtotal' => (float) $item->line_subtotal,
                    'discount_amount' => (float) $item->discount_amount,
                    'line_total' => (float) $item->line_total,
                    'primary_image_url' => $item->productVariant?->getPrimaryImageUrl('small'),
                ];
            })->all(),
            'pricing' => [
                'subtotal' => (float) $this->subtotal,
                'discount_amount' => (float) $this->discount_amount,
                'promotion_discount_total' => (float) $this->promotion_discount_total,
                'coupon_discount_total' => (float) $this->coupon_discount_total,
                'bundle_discount_total' => (float) $this->bundle_discount_total,
                'delivery_fee' => (float) $this->delivery_fee,
                'gift_wrapping_fee' => 0.0,
                'total' => (float) $this->total,
                'coupon_code' => $this->coupon_code_snapshot,
            ],
            'currency' => [
                'id' => $this->currency_id,
                'code' => $this->currency?->code ?? 'YER',
                'symbol' => ($this->currency?->code ?? 'YER') === 'YER'
                    ? 'ر.ي'
                    : ($this->currency?->symbol ?? 'ر.ي'),
            ],
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
