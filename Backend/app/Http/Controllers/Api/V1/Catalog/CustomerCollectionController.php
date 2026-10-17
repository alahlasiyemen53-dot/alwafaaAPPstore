<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\ApiErrorCode;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\VariantPrice;
use App\Services\CurrencyConverter;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerCollectionController extends Controller
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    public function index(): JsonResponse
    {
        $collections = ProductCollection::query()
            ->currentlyVisible()
            ->with('media')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $items = $collections->map(fn (ProductCollection $collection): array => [
            'id' => $collection->id,
            'name_ar' => $collection->name_ar,
            'name_en' => $collection->name_en,
            'slug' => $collection->slug,
            'description_ar' => $collection->description_ar,
            'cover_url' => $collection->getCoverUrl('small'),
            'sort_order' => $collection->sort_order,
        ])->all();

        return ApiResponse::success(['items' => $items], 'تم استرجاع مجموعات المنتجات.');
    }

    public function show(Request $request, int|string $collection): JsonResponse
    {
        $model = $this->resolveCollection($collection);
        if ($model === null || ! $model->is_active) {
            return ApiResponse::error(
                ApiErrorCode::ResourceNotFound->defaultMessage(),
                ApiErrorCode::ResourceNotFound,
                404
            );
        }

        // Enforce schedule visibility for customers.
        $visible = ProductCollection::query()
            ->currentlyVisible()
            ->whereKey($model->id)
            ->exists();
        if (! $visible) {
            return ApiResponse::error(
                ApiErrorCode::ResourceNotFound->defaultMessage(),
                ApiErrorCode::ResourceNotFound,
                404
            );
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));

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

        $paginator = Product::query()
            ->select('products.*')
            ->selectSub($minPriceSubquery, 'min_price')
            ->selectSub($maxPriceSubquery, 'max_price')
            ->join('product_collection_items', 'product_collection_items.product_id', '=', 'products.id')
            ->where('product_collection_items.collection_id', $model->id)
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
            ->orderBy('product_collection_items.sort_order')
            ->orderBy('product_collection_items.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $model->load('media');

        return ApiResponse::success([
            'collection' => [
                'id' => $model->id,
                'name_ar' => $model->name_ar,
                'name_en' => $model->name_en,
                'slug' => $model->slug,
                'description_ar' => $model->description_ar,
                'cover_url' => $model->getCoverUrl('medium'),
            ],
            'items' => CustomerProductListResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'تم استرجاع تفاصيل المجموعة.');
    }

    private function resolveCollection(int|string $collection): ?ProductCollection
    {
        if (is_numeric($collection)) {
            return ProductCollection::query()->find((int) $collection);
        }

        return ProductCollection::query()->where('slug', (string) $collection)->first();
    }
}
