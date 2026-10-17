<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use App\Enums\OrderPaymentStatus;
use App\Models\CustomerPaymentMethod;
use App\Models\Order;
use App\Services\Order\CustomerOrderTrackingMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class CustomerOrderDetailResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly CustomerOrderTrackingMapper $trackingMapper)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $address = $this->deliveryAddress;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->trackingMapper->current($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'delivery_address' => $address === null ? null : ['contact_name' => $address->contact_name, 'contact_phone' => $address->contact_phone, 'formatted_address' => $address->formatted_address],
            'gift_wrapping' => ['requested' => (bool) $this->gift_wrapping_requested, 'message' => $this->gift_message_snapshot, 'is_free' => true],
            'payment_method' => CustomerPaymentMethod::presentationForCode($this->payment_method_code),
            'payment_status' => $this->payment_status instanceof OrderPaymentStatus
                ? $this->payment_status->value
                : ($this->payment_status ?? OrderPaymentStatus::Unpaid->value),
            'payment_provider_reference' => $this->payment_provider_reference,
            'shipping_method' => [
                'code' => $this->shipping_method_code ?? 'standard',
                'name_ar' => $this->shipping_method_name_ar ?? 'توصيل قياسي',
            ],
            'items' => $this->items->map(function ($item): array {
                $variant = $item->productVariant;

                return [
                    'product_variant_id' => $item->product_variant_id,
                    'product_id' => $variant?->product_id,
                    'product_name' => $item->product_name_snapshot,
                    'variant_name' => $item->variant_name_snapshot,
                    'sku' => $item->sku_snapshot,
                    'fulfillment_mode' => $item->fulfillment_mode_snapshot?->value,
                    'quantity' => (float) $item->quantity,
                    'original_unit_price' => (float) $item->original_unit_price,
                    'unit_price' => (float) $item->unit_price,
                    'line_subtotal' => (float) $item->line_subtotal,
                    'discount_amount' => (float) $item->discount_amount,
                    'line_total' => (float) $item->line_total,
                    'primary_image_url' => $variant?->getPrimaryImageUrl('small'),
                    'supplier_code' => $item->sourcingSupplier?->code ?? $variant?->supplier?->code,
                    'supplier_name' => $item->supplier_name_snapshot,
                    'supplier_item_number' => $item->supplier_item_number_snapshot
                        ?? $variant?->supplier_item_number,
                ];
            })->all(),
            'pricing' => ['subtotal' => (float) $this->subtotal, 'discount_amount' => (float) $this->discount_amount, 'delivery_fee' => (float) $this->delivery_fee, 'total' => (float) $this->total],
            'currency' => [
                'code' => $this->currency?->code,
                'symbol' => $this->currency?->code === 'YER'
                    ? 'ر.ي'
                    : ($this->currency?->symbol ?? 'ر.ي'),
            ],
            'customer_confirm_deadline_at' => $this->customer_confirm_deadline_at?->toIso8601String(),
            'can_confirm_delivery' => $this->status?->canBeCustomerConfirmed() ?? false,
            'can_rate' => ($this->status?->value ?? '') === 'delivered',
        ];
    }
}
