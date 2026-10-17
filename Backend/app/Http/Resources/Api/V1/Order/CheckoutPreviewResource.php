<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CheckoutPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'can_checkout' => (bool) ($this['can_checkout'] ?? true),
            'warnings' => $this['warnings'] ?? [],
            'delivery_address' => $this['delivery_address'] ?? null,
            'shipping_methods' => $this['shipping_methods'] ?? [],
            'selected_shipping_method' => $this['selected_shipping_method'] ?? null,
            'payment_methods' => $this['payment_methods'] ?? [],
            'gift_wrapping' => $this['gift_wrapping'] ?? null,
            'items' => $this['items'] ?? [],
            'items_count' => $this['items_count'] ?? count($this['items'] ?? []),
            'total_quantity' => $this['total_quantity'] ?? 0,
            'pricing' => $this['pricing'] ?? [],
            'currency' => $this['currency'] ?? [],
        ];
    }
}
