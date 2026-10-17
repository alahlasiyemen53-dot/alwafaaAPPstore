<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Promotion;
use App\Services\DeepLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeepLinkWebController extends Controller
{
    public function __construct(
        private readonly DeepLinkService $deepLinkService,
    ) {}

    public function product(Request $request, int|string $product): View
    {
        $query = Product::query()
            ->with([
                'brand',
                'category',
                'media',
                'variants.prices',
                'defaultVariant.prices',
            ]);

        /** @var Product|null $model */
        $model = is_numeric($product)
            ? $query->find((int) $product)
            : $query->where('slug', $product)->orWhere('id', $product)->first();

        if ($model === null) {
            throw new NotFoundHttpException('المنتج غير موجود.');
        }

        $title = $model->name_ar ?: $model->name_en ?: 'منتج فاخر من لمّة';
        $description = $model->description_ar
            ?: $model->description_en
            ?: 'تسوق أرقى منتجات الجمال والعطور الفاخرة عبر تطبيق لمّة.';

        // Primary media or first media image
        $image = $model->getFirstMediaUrl('product_images', 'large')
            ?: $model->getFirstMediaUrl('product_images', 'medium')
            ?: $model->getFirstMediaUrl('product_images')
            ?: asset('images/logo.png');

        // Extract price from default variant or first variant
        $variant = $model->defaultVariant ?? $model->variants->first();
        $price = null;
        $currency = 'ر.س';
        if ($variant !== null && $variant->relationLoaded('prices')) {
            $priceModel = $variant->prices->first();
            if ($priceModel !== null) {
                $price = $priceModel->price;
            }
        }

        $categoryName = $model->category?->name_ar ?? $model->brand?->name_ar;

        return view('deep-link.landing', [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'price' => $price,
            'currency' => $currency,
            'categoryName' => $categoryName,
            'badgeLabel' => 'منتج مختار',
            'canonicalUrl' => $this->deepLinkService->productUrl($model->id),
            'schemeUrl' => $this->deepLinkService->productCustomScheme($model->id),
            'intentUrl' => $this->deepLinkService->productIntentUrl($model->id),
            'storeUrls' => $this->deepLinkService->resolveStoreUrls(),
            'redirectDelayMs' => config('deep_link.redirect_delay_ms', 0),
        ]);
    }

    public function offer(Request $request, int|string $offer): View
    {
        $query = Promotion::query();
        /** @var Promotion|null $model */
        $model = is_numeric($offer)
            ? $query->find((int) $offer)
            : $query->where('code', $offer)->orWhere('id', $offer)->first();

        if ($model === null) {
            throw new NotFoundHttpException('العرض غير موجود.');
        }

        $title = $model->name ?: 'عرض خاص من لمّة';
        $description = $model->description
            ?: 'اكتشف أفضل الخصومات والعروض الحصرية المتوفرة الآن في تطبيق لمّة.';

        $image = asset('images/logo.png');

        return view('deep-link.landing', [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'price' => null,
            'currency' => 'ر.س',
            'categoryName' => 'عروض حصرية',
            'badgeLabel' => 'عرض ترويجي',
            'canonicalUrl' => $this->deepLinkService->offerUrl($model->id),
            'schemeUrl' => $this->deepLinkService->offerCustomScheme($model->id),
            'intentUrl' => $this->deepLinkService->offerIntentUrl($model->id),
            'storeUrls' => $this->deepLinkService->resolveStoreUrls(),
            'redirectDelayMs' => config('deep_link.redirect_delay_ms', 0),
        ]);
    }

    public function collection(Request $request, int|string $collection): View
    {
        $query = ProductCollection::query()->with('media');
        /** @var ProductCollection|null $model */
        $model = is_numeric($collection)
            ? $query->find((int) $collection)
            : $query->where('slug', $collection)->orWhere('id', $collection)->first();

        if ($model === null) {
            throw new NotFoundHttpException('المجموعة غير موجودة.');
        }

        $title = $model->name_ar ?: $model->name_en ?: 'مجموعة مختارة من لمّة';
        $description = $model->description_ar
            ?: 'تصفح أحدث التشكيلات الحصرية عبر تطبيق لمّة.';

        $image = $model->getCoverUrl('large')
            ?: $model->getCoverUrl('small')
            ?: asset('images/logo.png');

        return view('deep-link.landing', [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'price' => null,
            'currency' => 'ر.س',
            'categoryName' => 'تشكيلات فاخرة',
            'badgeLabel' => 'مجموعة منتجات',
            'canonicalUrl' => $this->deepLinkService->collectionUrl($model->id),
            'schemeUrl' => $this->deepLinkService->collectionCustomScheme($model->id),
            'intentUrl' => $this->deepLinkService->collectionIntentUrl($model->id),
            'storeUrls' => $this->deepLinkService->resolveStoreUrls(),
            'redirectDelayMs' => config('deep_link.redirect_delay_ms', 0),
        ]);
    }

    public function assetLinks(): JsonResponse
    {
        return response()->json(
            $this->deepLinkService->getAssetLinksPayload(),
            200,
            ['Content-Type' => 'application/json'],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        return response()->json(
            $this->deepLinkService->getAppleAppSiteAssociationPayload(),
            200,
            ['Content-Type' => 'application/json'],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }
}
