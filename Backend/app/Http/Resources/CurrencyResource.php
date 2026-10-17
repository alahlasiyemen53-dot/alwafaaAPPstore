<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->resource;

        return $currency instanceof Currency ? $currency->toArray() : [];
    }
}
