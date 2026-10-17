<?php

namespace App\Actions;

use App\Data\ProductVariantData;
use App\Enums\ProductVariantFulfillmentMode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Rules\VariantPriceRule;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Services\VariantInvariantValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepository $variants,
        private readonly VariantInvariantValidator $validator,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AuditLogger $auditLogger,
        private readonly \App\Services\SkuGenerator $skuGenerator = new \App\Services\SkuGenerator,
    ) {}

    public function execute(ProductVariantData $data): ProductVariant
    {
        $comparePrice = $data->compareAtPrice !== null ? (float) $data->compareAtPrice : null;
        if (! VariantPriceRule::passes((float) $data->price, $comparePrice)) {
            throw ValidationException::withMessages([
                'price' => ['سعر المنتج يجب أن يكون أكبر من أو يساوي الصفر، وسعر المقارنة يجب أن يكون أعلى من السعر.'],
            ]);
        }

        $attributeMap = $this->validator->validateAttributeValues($data->attributeValueIds);
        $this->validator->validateUniqueCombination($data->productId, $data->attributeValueIds);

        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        // If this is the first variant of the product, make it default automatically
        $existingCount = ProductVariant::query()->where('product_id', $data->productId)->count();
        $isDefault = $data->isDefault || $existingCount === 0;

        $sku = trim($data->sku) !== '' ? strtoupper(trim($data->sku)) : $this->skuGenerator->generate();

        $supplierId = $data->supplierId;
        if ($data->fulfillmentMode === ProductVariantFulfillmentMode::OnDemand && $supplierId === null) {
            $productSupplierId = Product::query()->whereKey($data->productId)->value('supplier_id');
            if ($productSupplierId !== null) {
                $supplierId = (int) $productSupplierId;
            }
        }

        $adjustedData = new ProductVariantData(
            productId: $data->productId,
            sku: $sku,
            barcode: $data->barcode,
            status: $data->status,
            isDefault: $isDefault,
            attributeValueIds: $data->attributeValueIds,
            price: $data->price,
            compareAtPrice: $data->compareAtPrice,
            fulfillmentMode: $data->fulfillmentMode,
            supplierId: $supplierId,
            supplierItemNumber: $data->supplierItemNumber,
        );

        return DB::transaction(function () use ($adjustedData, $attributeMap, $baseCurrency): ProductVariant {
            $variant = $this->variants->create($adjustedData, $attributeMap, $baseCurrency->id);
            $this->auditLogger->record('admin.product_variant.created', $variant, metadata: [
                'base_currency' => $baseCurrency->code,
                'price' => $adjustedData->price,
                'is_default' => $adjustedData->isDefault,
                'fulfillment_mode' => $adjustedData->fulfillmentMode->value,
                'supplier_id' => $adjustedData->supplierId,
            ]);

            return $variant;
        });
    }
}
