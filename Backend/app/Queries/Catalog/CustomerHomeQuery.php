<?php

declare(strict_types=1);

namespace App\Queries\Catalog;

use App\Enums\HomeSectionType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HomeBanner;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class CustomerHomeQuery
{
    public function __construct(
        private readonly ListCustomerProductsQuery $productsQuery,
        private readonly GetCustomerRecentlyViewedQuery $recentlyViewedQuery,
    ) {}

    /**
     * @return array{
     *     banners: Collection<int, HomeBanner>,
     *     categories: Collection<int, Category>,
     *     best_sellers: array<Product>,
     *     new_arrivals: array<Product>,
     *     exclusive: array<Product>,
     *     brands: Collection<int, Brand>,
     *     sections: list<array{type: string, title_ar: string, title_en: ?string, data: mixed}>,
     * }
     */
    public function execute(?Customer $customer = null): array
    {
        $legacy = Cache::remember('customer.home.legacy.v1', 60, function (): array {
            return $this->buildLegacyPayload();
        });

        $sectionDefs = HomeSection::query()->active()->ordered()->get();
        if ($sectionDefs->isEmpty()) {
            $sectionDefs = $this->defaultSectionDefinitions();
        }

        $sections = [];
        foreach ($sectionDefs as $section) {
            $type = $section instanceof HomeSection
                ? $section->type
                : HomeSectionType::from((string) $section['type']);
            $titleAr = $section instanceof HomeSection ? $section->title_ar : (string) $section['title_ar'];
            $titleEn = $section instanceof HomeSection ? $section->title_en : ($section['title_en'] ?? null);
            $config = $section instanceof HomeSection
                ? ($section->configuration ?? [])
                : ($section['configuration'] ?? []);

            $data = match ($type) {
                HomeSectionType::Banners => $legacy['banners'],
                HomeSectionType::Categories => $legacy['categories'],
                HomeSectionType::Brands => $legacy['brands'],
                HomeSectionType::BestSellers => $legacy['best_sellers'],
                HomeSectionType::NewProducts => $legacy['new_arrivals'],
                HomeSectionType::ExclusiveProducts => $legacy['exclusive'],
                HomeSectionType::Collection => $this->resolveCollectionSection($config),
                HomeSectionType::RecentlyViewed => $customer !== null
                    ? $this->recentlyViewedQuery->execute($customer, 10)->all()
                    : [],
            };

            // Skip empty optional rails.
            if (
                in_array($type, [HomeSectionType::Collection, HomeSectionType::RecentlyViewed], true)
                && (empty($data) || (is_countable($data) && count($data) === 0))
            ) {
                continue;
            }

            $sections[] = [
                'type' => $type->value,
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'data' => $data,
                'configuration' => $config,
            ];
        }

        return [
            ...$legacy,
            'sections_ordered' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    private function buildLegacyPayload(): array
    {
        $banners = HomeBanner::query()
            ->currentlyScheduled()
            ->ordered()
            ->with('media')
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(8)
            ->with('media')
            ->get();

        $this->loadBannerDestinations($banners);

        $brands = Brand::query()
            ->where('is_active', true)
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->limit(12)
            ->get();

        return [
            'banners' => $banners,
            'categories' => $categories,
            'brands' => $brands,
            'best_sellers' => $this->productsQuery->topSelling(10)->all(),
            'new_arrivals' => $this->productsQuery->newest(10)->all(),
            'exclusive' => $this->productsQuery->paginate(['badge' => 'exclusive', 'per_page' => 10])->items(),
        ];
    }

    /** @param array<string, mixed> $config */
    private function resolveCollectionSection(array $config): ?ProductCollection
    {
        $collectionId = isset($config['collection_id']) ? (int) $config['collection_id'] : 0;
        if ($collectionId <= 0) {
            return null;
        }

        return ProductCollection::query()
            ->currentlyVisible()
            ->with(['media', 'products' => fn ($q) => $q->limit(10)])
            ->find($collectionId);
    }

    /** @return list<array{type: string, title_ar: string, title_en: ?string, configuration: array<string, mixed>}> */
    private function defaultSectionDefinitions(): array
    {
        return [
            ['type' => HomeSectionType::Banners->value, 'title_ar' => 'البنرات', 'title_en' => 'Banners', 'configuration' => []],
            ['type' => HomeSectionType::Categories->value, 'title_ar' => 'التصنيفات', 'title_en' => 'Categories', 'configuration' => []],
            ['type' => HomeSectionType::Brands->value, 'title_ar' => 'العلامات التجارية', 'title_en' => 'Brands', 'configuration' => []],
            ['type' => HomeSectionType::BestSellers->value, 'title_ar' => 'الأكثر مبيعاً', 'title_en' => 'Best Sellers', 'configuration' => []],
            ['type' => HomeSectionType::NewProducts->value, 'title_ar' => 'وصل حديثاً', 'title_en' => 'New Arrivals', 'configuration' => []],
            ['type' => HomeSectionType::ExclusiveProducts->value, 'title_ar' => 'حصريات الوفاء استور', 'title_en' => 'Exclusive', 'configuration' => []],
            ['type' => HomeSectionType::RecentlyViewed->value, 'title_ar' => 'شاهدتها مؤخراً', 'title_en' => 'Recently Viewed', 'configuration' => []],
        ];
    }

    /** @param Collection<int, HomeBanner> $banners */
    private function loadBannerDestinations(Collection $banners): void
    {
        $references = $banners
            ->filter(fn (HomeBanner $banner): bool => $banner->action_reference !== null && $banner->action_type->value !== 'none')
            ->groupBy(fn (HomeBanner $banner): string => $banner->action_type->value)
            ->map(fn (Collection $group): array => $group->pluck('action_reference')->filter(fn (string $reference): bool => is_numeric($reference))->map('intval')->unique()->all());

        $destinationModels = $references->map(
            fn (array $ids, string $type): Collection => match ($type) {
                'product' => Product::query()->whereKey($ids)->get()->keyBy('id'),
                'category' => Category::query()->whereKey($ids)->get()->keyBy('id'),
                'promotion' => Promotion::query()->whereKey($ids)->get()->keyBy('id'),
                default => new Collection(),
            },
        );

        $banners->each(function (HomeBanner $banner) use ($destinationModels): void {
            $destination = $destinationModels->get($banner->action_type->value);
            $banner->setRelation('customerDestination', $destination?->get((int) $banner->action_reference));
        });
    }
}
