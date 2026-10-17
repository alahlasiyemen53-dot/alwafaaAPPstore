<?php

namespace App\Services\Search;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Product;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class SearchResultRehydrationService
{
    public function __construct(
        private readonly ?CurrencyConverter $currencyConverter = null,
    ) {}

    /**
     * Rehydrate products from MySQL in the exact order of given product IDs.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, Product>
     */
    public function rehydrate(array $productIds, bool $activeOnly = true): Collection
    {
        $uniqueIds = array_values(array_unique(array_filter($productIds)));

        if (empty($uniqueIds)) {
            return new Collection;
        }

        $currencyConverter = $this->currencyConverter ?? app(CurrencyConverter::class);
        $baseCurrency = $currencyConverter->getBaseCurrency();
        $currencyId = $baseCurrency->id;

        $minPriceSubquery = VariantPrice::query()
            ->selectRaw('MIN(variant_prices.price)')
            ->join('product_variants', 'product_variants.id', '=', 'variant_prices.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.status', ProductVariantStatus::Active->value)
            ->where('variant_prices.currency_id', $currencyId)
            ->where('variant_prices.is_active', true);

        $maxPriceSubquery = VariantPrice::query()
            ->selectRaw('MAX(variant_prices.price)')
            ->join('product_variants', 'product_variants.id', '=', 'variant_prices.product_variant_id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.status', ProductVariantStatus::Active->value)
            ->where('variant_prices.currency_id', $currencyId)
            ->where('variant_prices.is_active', true);

        $query = Product::query()
            ->select('products.*')
            ->selectSub($minPriceSubquery, 'min_price')
            ->selectSub($maxPriceSubquery, 'max_price')
            ->with([
                'brand:id,name_ar,name_en,slug,is_active',
                'category:id,name_ar,name_en,slug,is_active',
                'media',
                'variants' => fn ($vq) => $vq
                    ->where('status', ProductVariantStatus::Active)
                    ->with([
                        'prices' => fn ($pq) => $pq->where('currency_id', $currencyId)->where('is_active', true),
                        'inventories',
                        'supplier',
                    ]),
            ])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->whereIn('products.id', $uniqueIds);

        if ($activeOnly) {
            $query->where('products.status', ProductStatus::Active)
                ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
        }

        /** @var Collection<int, Product> $products */
        $products = $query->get()->keyBy('id');

        // Preserve original candidate order
        $ordered = new Collection;
        foreach ($uniqueIds as $id) {
            if ($products->has($id)) {
                $ordered->push($products->get($id));
            }
        }

        return $ordered;
    }
}
