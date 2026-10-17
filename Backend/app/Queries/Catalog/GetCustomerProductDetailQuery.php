<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Product;
use App\Queries\GetProductRatingSummaryQuery;
use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Builder;

final class GetCustomerProductDetailQuery
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly GetProductRatingSummaryQuery $ratingSummaryQuery,
    ) {}

    /**
     * @return array{product: Product, rating_summary: array<string, mixed>}|null
     */
    public function find(int|string $idOrSlug): ?array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $currencyId = $baseCurrency->id;

        $query = Product::query()
            ->where('status', ProductStatus::Active)
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));

        if (is_numeric($idOrSlug)) {
            $query->where('id', (int) $idOrSlug);
        } else {
            $query->where('slug', $idOrSlug);
        }

        /** @var Product|null $product */
        $product = $query->with([
            'brand:id,name_ar,name_en,slug,is_active',
            'brand.media',
            'category:id,name_ar,name_en,slug,parent_id,is_active',
            'media',
            'variants' => fn ($vq) => $vq
                ->where('status', ProductVariantStatus::Active)
                ->with([
                    'prices' => fn ($pq) => $pq->where('currency_id', $currencyId)->where('is_active', true)->with('currency'),
                    'inventories',
                    'supplier',
                    'attributeValues.attribute',
                    'media',
                ]),
        ])->first();

        if ($product === null) {
            return null;
        }

        $ratingSummary = $this->ratingSummaryQuery->getForProduct($product);

        return [
            'product' => $product,
            'rating_summary' => $ratingSummary,
        ];
    }
}
