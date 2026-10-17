<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class GetRelatedProductsQuery
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @return Collection<int, Product>|null Returns null if target product is not found or inactive.
     */
    public function execute(int|string $idOrSlug, ?int $limit = 8): ?Collection
    {
        // 1. Resolve current target product
        $targetQuery = Product::query()
            ->where('status', ProductStatus::Active);

        if (is_numeric($idOrSlug)) {
            $targetQuery->where('id', (int) $idOrSlug);
        } else {
            $targetQuery->where('slug', $idOrSlug);
        }

        /** @var Product|null $targetProduct */
        $targetProduct = $targetQuery->first();

        if ($targetProduct === null) {
            return null; // Signals 404 Product Not Found
        }

        // 2. If target product has no category, return empty collection
        if ($targetProduct->category_id === null) {
            return new Collection();
        }

        // Verify target product's category is active
        $isCategoryActive = Category::query()
            ->where('id', $targetProduct->category_id)
            ->where('is_active', true)
            ->exists();

        if (! $isCategoryActive) {
            return new Collection();
        }

        // 3. Prepare bounded limit (default 8, max 20, min 1)
        $boundedLimit = min(max((int) ($limit ?? 8), 1), 20);

        // 4. Base currency for pricing subquery
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

        // 5. Query customer-visible related products in the same category, excluding current product
        return Product::query()
            ->select('products.*')
            ->selectSub($minPriceSubquery, 'min_price')
            ->selectSub($maxPriceSubquery, 'max_price')
            ->where('products.status', ProductStatus::Active)
            ->where('products.category_id', $targetProduct->category_id)
            ->where('products.id', '!=', $targetProduct->id)
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true))
            ->with([
                'brand:id,name_ar,name_en,slug,is_active',
                'brand.media',
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
            ->orderBy('is_best_seller', 'desc')
            ->orderBy('is_new', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take($boundedLimit)
            ->get();
    }
}
