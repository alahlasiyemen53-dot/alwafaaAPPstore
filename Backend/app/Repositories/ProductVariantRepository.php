<?php

namespace App\Repositories;

use App\Data\ProductVariantData;
use App\Data\UpdateVariantPriceData;
use App\Enums\ProductVariantStatus;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Support\Facades\DB;

final class ProductVariantRepository
{
    /**
     * @param  array<int, int>  $attributeMap  [attribute_id => attribute_value_id]
     */
    public function create(ProductVariantData $data, array $attributeMap, int $baseCurrencyId): ProductVariant
    {
        return DB::transaction(function () use ($data, $attributeMap, $baseCurrencyId): ProductVariant {
            if ($data->isDefault) {
                ProductVariant::query()
                    ->where('product_id', $data->productId)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            $variant = ProductVariant::query()->create([
                'product_id' => $data->productId,
                'sku' => $data->sku,
                'barcode' => $data->barcode,
                'status' => $data->status,
                'is_default' => $data->isDefault,
                'fulfillment_mode' => $data->fulfillmentMode,
                'supplier_id' => $data->supplierId,
                'supplier_item_number' => $data->supplierItemNumber,
            ]);

            $this->syncAttributeValues($variant, $attributeMap);
            $this->saveBasePrice($variant, $data->price, $data->compareAtPrice, $baseCurrencyId);

            return $variant->fresh(['product', 'attributeValues.attribute', 'prices.currency', 'supplier']);
        });
    }

    /**
     * @param  array<int, int>  $attributeMap  [attribute_id => attribute_value_id]
     */
    public function update(
        ProductVariant $variant,
        ProductVariantData $data,
        array $attributeMap,
        int $baseCurrencyId,
    ): ProductVariant {
        return DB::transaction(function () use ($variant, $data, $attributeMap, $baseCurrencyId): ProductVariant {
            if ($data->isDefault && ! $variant->is_default) {
                ProductVariant::query()
                    ->where('product_id', $variant->product_id)
                    ->where('id', '!=', $variant->id)
                    ->lockForUpdate()
                    ->update(['is_default' => false]);
            }

            $variant->update([
                'barcode' => $data->barcode,
                'status' => $data->status,
                'is_default' => $data->isDefault,
                'fulfillment_mode' => $data->fulfillmentMode,
                'supplier_id' => $data->supplierId,
                'supplier_item_number' => $data->supplierItemNumber,
            ]);

            $this->syncAttributeValues($variant, $attributeMap);
            $this->saveBasePrice($variant, $data->price, $data->compareAtPrice, $baseCurrencyId);

            return $variant->fresh(['product', 'attributeValues.attribute', 'prices.currency', 'supplier']);
        });
    }

    public function setDefault(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant): ProductVariant {
            ProductVariant::query()
                ->where('product_id', $variant->product_id)
                ->lockForUpdate()
                ->update(['is_default' => false]);

            $variant->update(['is_default' => true]);

            return $variant->fresh();
        });
    }

    public function transitionStatus(ProductVariant $variant, ProductVariantStatus $status): ProductVariant
    {
        $variant->update(['status' => $status]);

        return $variant->fresh();
    }

    public function saveBasePrice(
        ProductVariant $variant,
        string $price,
        ?string $compareAtPrice,
        int $baseCurrencyId,
    ): VariantPrice {
        return VariantPrice::query()->updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'currency_id' => $baseCurrencyId,
            ],
            [
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'is_active' => true,
            ],
        );
    }

    public function updatePrice(
        ProductVariant $variant,
        UpdateVariantPriceData $priceData,
        int $baseCurrencyId,
    ): VariantPrice {
        return $this->saveBasePrice(
            $variant,
            $priceData->price,
            $priceData->compareAtPrice,
            $baseCurrencyId,
        );
    }

    /**
     * @param  array<int, int>  $attributeMap  [attribute_id => attribute_value_id]
     */
    private function syncAttributeValues(ProductVariant $variant, array $attributeMap): void
    {
        DB::table('product_variant_values')->where('product_variant_id', $variant->id)->delete();

        $rows = [];
        foreach ($attributeMap as $attributeId => $attributeValueId) {
            $rows[] = [
                'product_variant_id' => $variant->id,
                'attribute_id' => $attributeId,
                'attribute_value_id' => $attributeValueId,
            ];
        }

        if (! empty($rows)) {
            DB::table('product_variant_values')->insert($rows);
        }
    }
}
