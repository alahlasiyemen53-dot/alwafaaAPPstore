<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerCartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cart_id' => $this['cart_id'],
            'items' => CustomerCartItemResource::collection($this['items']),
            'items_count' => $this['items_count'],
            'total_quantity' => $this['total_quantity'],
            'currency' => $this['currency'],
            'pricing' => $this['pricing'],
            'coupon' => $this['coupon'],
        ];
    }
}
