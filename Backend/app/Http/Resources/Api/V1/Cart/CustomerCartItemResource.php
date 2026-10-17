<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerCartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'product_variant_id' => $this['product_variant_id'],
            'quantity' => $this['quantity'],
            'unit_price' => $this['unit_price'],
            'line_subtotal' => $this['line_subtotal'],
            'item_discount' => $this['item_discount'],
            'line_total' => $this['line_total'],
            'availability' => $this['availability'],
            'product' => $this['product'],
            'variant' => $this['variant'],
        ];
    }
}
