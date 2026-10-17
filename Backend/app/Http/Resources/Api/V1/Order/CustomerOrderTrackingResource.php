<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Order;

use App\Models\Order;
use App\Services\Order\CustomerOrderTrackingMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class CustomerOrderTrackingResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly CustomerOrderTrackingMapper $trackingMapper)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return ['order_number' => $this->order_number, ...$this->trackingMapper->current($this->resource), 'timeline' => $this->trackingMapper->timeline($this->resource)];
    }
}
