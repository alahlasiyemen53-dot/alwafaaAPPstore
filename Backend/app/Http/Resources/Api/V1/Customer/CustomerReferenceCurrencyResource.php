<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Currency;
use App\Services\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Currency
 */
final class CustomerReferenceCurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $converter = app(CurrencyConverter::class);
        $baseCurrency = $converter->getBaseCurrency();
        $isBase = $this->id === $baseCurrency->id;
        $rateInfo = $isBase ? null : $converter->resolveRate($this->resource, $baseCurrency);
        $rate = $isBase ? '1.00000000' : (is_array($rateInfo) && $rateInfo['direction'] === 'target_to_base' ? $rateInfo['rate'] : null);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'symbol' => $this->code === 'YER' ? 'ر.ي' : $this->symbol,
            'decimal_places' => $this->decimal_places,
            'is_base' => $isBase,
            'rate' => $rate,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
