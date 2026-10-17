<?php

namespace App\Http\Resources\Api\V1\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAuthTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'] ?? null,
            'expires_in' => $this->resource['expires_in'],
            'customer' => new CustomerResource($this->resource['customer']),
        ];
    }
}
