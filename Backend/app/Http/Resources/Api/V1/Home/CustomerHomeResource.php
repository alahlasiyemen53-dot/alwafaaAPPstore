<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Home;

use App\Enums\HomeSectionType;
use App\Http\Resources\Api\V1\Catalog\CustomerBrandResource;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use App\Models\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerHomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $featuredCategories = [];
        if (isset($this['categories'])) {
            foreach ($this['categories'] as $category) {
                $imageUrl = method_exists($category, 'getImageUrl')
                    ? $category->getImageUrl('small')
                    : null;

                $featuredCategories[] = [
                    'id' => $category->id,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name_en,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'image_url' => $imageUrl,
                ];
            }
        }

        $orderedSections = [];
        foreach ($this['sections_ordered'] ?? [] as $section) {
            $type = (string) ($section['type'] ?? '');
            $orderedSections[] = [
                'type' => $type,
                'title_ar' => $section['title_ar'] ?? '',
                'title_en' => $section['title_en'] ?? null,
                'configuration' => $section['configuration'] ?? [],
                'data' => $this->transformSectionData($type, $section['data'] ?? null),
            ];
        }

        return [
            'banners' => CustomerHomeBannerResource::collection($this['banners'] ?? []),
            'featured_categories' => $featuredCategories,
            // Backward-compatible keyed sections.
            'sections' => [
                'best_sellers' => CustomerProductListResource::collection($this['best_sellers'] ?? []),
                'new_arrivals' => CustomerProductListResource::collection($this['new_arrivals'] ?? []),
                'exclusive' => CustomerProductListResource::collection($this['exclusive'] ?? []),
            ],
            // Server-driven ordered sections (allowlisted types).
            'home_sections' => $orderedSections,
        ];
    }

    private function transformSectionData(string $type, mixed $data): mixed
    {
        return match ($type) {
            HomeSectionType::Banners->value => CustomerHomeBannerResource::collection($data ?? []),
            HomeSectionType::Categories->value => $this->mapCategories($data),
            HomeSectionType::Brands->value => CustomerBrandResource::collection($data ?? []),
            HomeSectionType::BestSellers->value,
            HomeSectionType::NewProducts->value,
            HomeSectionType::ExclusiveProducts->value,
            HomeSectionType::RecentlyViewed->value => CustomerProductListResource::collection($data ?? []),
            HomeSectionType::Collection->value => $this->mapCollection($data),
            default => null,
        };
    }

    private function mapCategories(mixed $data): array
    {
        $featuredCategories = [];
        if (! is_iterable($data)) {
            return $featuredCategories;
        }

        foreach ($data as $category) {
            $imageUrl = method_exists($category, 'getImageUrl')
                ? $category->getImageUrl('small')
                : null;

            $featuredCategories[] = [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'image_url' => $imageUrl,
            ];
        }

        return $featuredCategories;
    }

    private function mapCollection(mixed $data): ?array
    {
        if (! $data instanceof ProductCollection) {
            return null;
        }

        return [
            'id' => $data->id,
            'name_ar' => $data->name_ar,
            'name_en' => $data->name_en,
            'slug' => $data->slug,
            'cover_url' => $data->getCoverUrl('small'),
            'products' => CustomerProductListResource::collection($data->products ?? []),
        ];
    }
}
