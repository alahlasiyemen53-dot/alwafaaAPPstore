<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use App\Models\Order;
use App\Services\Order\CustomerOrderTrackingMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class CustomerOrderSummaryResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly CustomerOrderTrackingMapper $trackingMapper)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->trackingMapper->current($this->resource)['status'],
            'created_at' => $this->created_at?->toIso8601String(),
            'items_count' => $this->items_count ?? $this->items->count(),
            'items' => $this->items->map(fn ($item) => ['product_name' => $item->product_name_snapshot, 'variant_name' => $item->variant_name_snapshot, 'quantity' => (float) $item->quantity])->all(),
            'total' => (float) $this->total,
            'currency' => [
                'code' => $this->currency?->code,
                'symbol' => $this->currency?->code === 'YER'
                    ? 'ر.ي'
                    : ($this->currency?->symbol ?? 'ر.ي'),
            ],
        ];
    }
}
