<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class GetCustomerRecentlyViewedQuery
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function execute(Customer $customer, ?int $limit = null): Collection
    {
        $limit = max(1, $limit ?? (int) config('customer_experience.recently_viewed_limit', 20));

        $baseCurrency = $this->currencyConverter->getBaseCurrency();
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

        return Product::query()
            ->select('products.*')
            ->selectSub($minPriceSubquery, 'min_price')
            ->selectSub($maxPriceSubquery, 'max_price')
            ->join('customer_recently_viewed_products', 'customer_recently_viewed_products.product_id', '=', 'products.id')
            ->where('customer_recently_viewed_products.customer_id', $customer->id)
            ->where('products.status', ProductStatus::Active)
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true))
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
            ->orderByDesc('customer_recently_viewed_products.last_viewed_at')
            ->limit($limit)
            ->get();
    }
}
