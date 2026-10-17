<?php

namespace App\Actions;

use App\Data\UpdateVariantPriceData;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Repositories\ProductVariantRepository;
use App\Rules\VariantPriceRule;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateVariantPricesAction
{
    public function __construct(
        private readonly ProductVariantRepository $variants,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant, UpdateVariantPriceData $priceData): VariantPrice
    {
        $comparePrice = $priceData->compareAtPrice !== null ? (float) $priceData->compareAtPrice : null;
        if (! VariantPriceRule::passes((float) $priceData->price, $comparePrice)) {
            throw ValidationException::withMessages([
                'price' => ['سعر المنتج يجب أن يكون أكبر من أو يساوي الصفر، وسعر المقارنة يجب أن يكون أعلى من السعر.'],
            ]);
        }

        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        return DB::transaction(function () use ($variant, $priceData, $baseCurrency): VariantPrice {
            /** @var VariantPrice|null $existingPrice */
            $existingPrice = $variant->prices()->where('currency_id', $baseCurrency->id)->first();
            $beforePrice = $existingPrice?->price;

            $price = $this->variants->updatePrice($variant, $priceData, $baseCurrency->id);

            $this->auditLogger->record('admin.variant_price.updated', $price, metadata: [
                'variant_id' => $variant->id,
                'currency_code' => $baseCurrency->code,
                'before_price' => $beforePrice,
                'after_price' => $price->price,
            ]);

            return $price;
        });
    }
}
