<?php

namespace App\Queries;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Collection;

final class AdminProductVariantQuery
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @return Collection<int, ProductVariant>
     */
    public function forProduct(Product $product): Collection
    {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->with([
                'product',
                'supplier',
                'attributeValues' => function ($q): void {
                    $q->with('attribute')->orderBy('sort_order');
                },
                'prices.currency',
                'media',
            ])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        try {
            $baseCurrency = $this->currencyConverter->getBaseCurrency();
        } catch (\RuntimeException) {
            $baseCurrency = null;
        }

        $activeCurrencies = Currency::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get();

        foreach ($variants as $variant) {
            $variant->setRelation('product', $product);

            /** @var VariantPrice|null $basePriceRecord */
            $basePriceRecord = $baseCurrency !== null ? $variant->prices->firstWhere('currency_id', $baseCurrency->id) : $variant->prices->first();
            $basePrice = $basePriceRecord?->price;

            $previews = [];
            if ($baseCurrency !== null) {
                foreach ($activeCurrencies as $currency) {
                    $previews[$currency->code] = $this->currencyConverter->convert(
                        $basePrice,
                        $baseCurrency,
                        $currency,
                    );
                }
            }

            $variant->setAttribute('base_price_record', $basePriceRecord);
            $variant->setAttribute('converted_previews', $previews);
        }

        return $variants;
    }

    public function activeCurrencies(): Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get();
    }
}
