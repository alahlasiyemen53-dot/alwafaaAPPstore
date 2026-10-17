<?php

namespace App\Http\Resources;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rate = $this->resource;
        if (! $rate instanceof ExchangeRate) {
            return [];
        }

        $effectiveAt = $rate->getAttribute('effective_at');

        return ['id' => $rate->id, 'rate' => $rate->rate, 'effective_at' => $effectiveAt instanceof \DateTimeInterface ? $effectiveAt->format(DATE_ATOM) : null, 'from_currency' => CurrencyResource::make($rate->fromCurrency), 'to_currency' => CurrencyResource::make($rate->toCurrency)];
    }
}
