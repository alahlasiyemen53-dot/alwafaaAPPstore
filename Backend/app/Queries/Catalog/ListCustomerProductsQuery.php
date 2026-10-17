<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListCustomerProductsQuery
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @param array{
     *     category_id?: list<int>|int|null,
     *     brand_id?: list<int>|int|null,
     *     badge?: string|null,
     *     availability?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null,
     *     page?: int|null
     * } $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters);
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->paginate($perPage);
    }

    /**
     * Latest active catalog products by creation time.
     *
     * @return Collection<int, Product>
     */
    public function newest(int $limit = 10): Collection
    {
        $limit = min(max($limit, 1), 50);

        return $this->buildQuery(['sort' => 'newest'])
            ->limit($limit)
            ->get();
    }

    /**
     * Top products by actual sold quantity (non-draft, non-cancelled orders).
     *
     * @return Collection<int, Product>
     */
    public function topSelling(int $limit = 10): Collection
    {
        $limit = min(max($limit, 1), 50);

        $rankedIds = OrderItem::query()
            ->select('product_variants.product_id')
            ->selectRaw('SUM(order_items.quantity) as sold_qty')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->whereNotIn('orders.status', [
                OrderStatus::Draft->value,
                OrderStatus::Cancelled->value,
            ])
            ->groupBy('product_variants.product_id')
            ->orderByDesc('sold_qty')
            ->orderBy('product_variants.product_id')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($rankedIds->isEmpty()) {
            return new Collection;
        }

        $products = $this->buildQuery([])
            ->whereIn('products.id', $rankedIds->all())
            ->get()
            ->keyBy('id');

        return new Collection(
            $rankedIds
                ->map(fn (int $id) => $products->get($id))
                ->filter()
                ->values()
                ->all()
        );
    }

    /**
     * @param array{
     *     category_id?: list<int>|int|null,
     *     brand_id?: list<int>|int|null,
     *     badge?: string|null,
     *     availability?: string|null,
     *     sort?: string|null
     * } $filters
     */
    private function buildQuery(array $filters = []): Builder
    {
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

        $query = Product::query()
            ->select('products.*')
            ->selectSub($minPriceSubquery, 'min_price')
            ->selectSub($maxPriceSubquery, 'max_price')
            ->where('products.status', ProductStatus::Active)
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
            ->withCount('approvedReviews');

        if (! empty($filters['category_id'])) {
            $inputIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                (array) $filters['category_id'],
            )));
            $inputIds = array_values(array_filter($inputIds, static fn (int $id): bool => $id >= 1));

            if ($inputIds !== []) {
                $expandedIds = Category::query()
                    ->where(function (Builder $q) use ($inputIds): void {
                        $q->whereIn('id', $inputIds)
                            ->orWhereIn('parent_id', $inputIds);
                    })
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                $query->whereIn('category_id', $expandedIds !== [] ? $expandedIds : $inputIds);
            }
        }

        if (! empty($filters['brand_id'])) {
            $brandIds = array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                (array) $filters['brand_id'],
            )));
            $brandIds = array_values(array_filter($brandIds, static fn (int $id): bool => $id >= 1));
            if (count($brandIds) === 1) {
                $query->where('brand_id', $brandIds[0]);
            } elseif ($brandIds !== []) {
                $query->whereIn('brand_id', $brandIds);
            }
        }

        if (! empty($filters['badge'])) {
            match ($filters['badge']) {
                'best_seller' => $query->where('is_best_seller', true),
                'new' => $query->where('is_new', true),
                'exclusive' => $query->where('is_exclusive', true),
                default => null,
            };
        }

        if (! empty($filters['availability'])) {
            $mode = $filters['availability'];
            if ($mode === 'in_stock') {
                $query->whereHas('variants', function (Builder $vq) {
                    $vq->where('status', ProductVariantStatus::Active)
                        ->where(function (Builder $sub) {
                            $sub->where('fulfillment_mode', 'stocked')
                                ->whereHas('inventories', fn ($iq) => $iq->whereRaw('(quantity - reserved_quantity) > 0'));
                        });
                });
            } elseif ($mode === 'on_demand') {
                $query->whereHas('variants', function (Builder $vq) {
                    $vq->where('status', ProductVariantStatus::Active)
                        ->where('fulfillment_mode', 'on_demand')
                        ->whereHas('supplier', fn ($sq) => $sq->where('is_active', true));
                });
            } elseif ($mode === 'any_available') {
                $query->whereHas('variants', function (Builder $vq) {
                    $vq->where('status', ProductVariantStatus::Active)
                        ->where(function (Builder $sub) {
                            $sub->where(function (Builder $s1) {
                                $s1->where('fulfillment_mode', 'stocked')
                                    ->whereHas('inventories', fn ($iq) => $iq->whereRaw('(quantity - reserved_quantity) > 0'));
                            })->orWhere(function (Builder $s2) {
                                $s2->where('fulfillment_mode', 'on_demand')
                                    ->whereHas('supplier', fn ($sq) => $sq->where('is_active', true));
                            });
                        });
                });
            }
        }

        $sort = $filters['sort'] ?? 'featured';
        match ($sort) {
            'price_asc' => $query->orderByRaw('min_price IS NULL, min_price ASC')->orderBy('id', 'asc'),
            'price_desc' => $query->orderByRaw('min_price IS NULL, min_price DESC')->orderBy('id', 'desc'),
            'rating' => $query->orderByRaw('approved_reviews_avg_rating IS NULL, approved_reviews_avg_rating DESC')->orderBy('id', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            default => $query->orderBy('is_best_seller', 'desc')
                ->orderBy('is_new', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc'),
        };

        return $query;
    }
}
