<?php

namespace App\Repositories;

use App\Data\CreateProductData;
use App\Data\UpdateProductData;
use App\Enums\ProductStatus;
use App\Models\Product;

final class ProductRepository
{
    public function create(CreateProductData $productData): Product
    {
        return Product::query()->create($this->attributes($productData));
    }

    public function update(Product $product, UpdateProductData $productData): Product
    {
        $product->update($this->attributes($productData));

        return $product->fresh(['brand', 'category']);
    }

    public function transition(Product $product, ProductStatus $status): Product
    {
        $product->update(['status' => $status]);

        return $product->fresh(['brand', 'category']);
    }

    public function updateMerchandising(Product $product, bool $isBestSeller, bool $isNew, bool $isExclusive): Product
    {
        $product->update([
            'is_best_seller' => $isBestSeller,
            'is_new' => $isNew,
            'is_exclusive' => $isExclusive,
        ]);

        return $product->fresh(['brand', 'category']);
    }

    private function attributes(CreateProductData|UpdateProductData $productData): array
    {
        $attributes = [
            'brand_id' => $productData->brandId,
            'category_id' => $productData->categoryId,
            'name_ar' => $productData->nameAr,
            'name_en' => $productData->nameEn,
            'description_ar' => $productData->descriptionAr,
            'description_en' => $productData->descriptionEn,
            'has_variants' => $productData->hasVariants,
            'is_best_seller' => $productData->isBestSeller,
            'is_new' => $productData->isNew,
            'is_exclusive' => $productData->isExclusive,
            'supplier_id' => $productData->supplierId,
            'supplier_item_number' => $productData->supplierItemNumber,
        ];

        if ($productData instanceof CreateProductData) {
            $attributes['slug'] = $productData->slug;
            $attributes['status'] = $productData->status;
        }

        return $attributes;
    }
}
