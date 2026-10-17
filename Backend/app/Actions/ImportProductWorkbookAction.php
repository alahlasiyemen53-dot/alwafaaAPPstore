<?php

namespace App\Actions;

use App\Data\CreateProductData;
use App\Data\ProductImportRowData;
use App\Data\ProductVariantData;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\SlugGenerator;
use Illuminate\Support\Facades\DB;

final class ImportProductWorkbookAction
{
    public function __construct(
        private readonly CreateProductAction $createProduct,
        private readonly CreateProductVariantAction $createVariant,
        private readonly RecordStockMovementAction $recordStockMovement,
        private readonly SlugGenerator $slugGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<int, ProductImportRowData> $rows
     * @return array{products: int, variants: int}
     */
    public function execute(array $rows, string $filename): array
    {
        return DB::transaction(function () use ($rows, $filename): array {
            $products = 0;
            $variants = 0;
            foreach ($this->groups($rows) as $group) {
                $productSupplierId = null;
                foreach ($group as $groupRow) {
                    if ($groupRow->supplierId !== null) {
                        $productSupplierId = $groupRow->supplierId;
                        break;
                    }
                }
                $product = $this->createProduct($group[0], $productSupplierId);
                $products++;
                foreach ($group as $row) {
                    $variantSupplierId = $row->fulfillmentMode === ProductVariantFulfillmentMode::OnDemand
                        ? ($row->supplierId ?? $product->supplier_id)
                        : null;
                    $variant = $this->createVariant->execute(new ProductVariantData(
                        productId: $product->id,
                        sku: '',
                        barcode: null,
                        status: $row->isActive ? ProductVariantStatus::Active : ProductVariantStatus::Inactive,
                        isDefault: false,
                        attributeValueIds: $row->attributeValueIds,
                        price: $row->price,
                        compareAtPrice: null,
                        fulfillmentMode: $row->fulfillmentMode,
                        supplierId: $variantSupplierId !== null ? (int) $variantSupplierId : null,
                        supplierItemNumber: $row->supplierItemNumber,
                    ));
                    $variants++;
                    if ($row->stockQuantity !== null && (float) $row->stockQuantity > 0) {
                        $this->recordStockMovement->execute(
                            $row->warehouseId,
                            $variant->id,
                            StockMovementType::AdjustmentIn,
                            StockMovementDirection::In,
                            (float) $row->stockQuantity,
                            $variant,
                            'excel_import',
                            'Excel product import',
                        );
                    }
                }
            }
            $this->auditLogger->record('admin.product_import.completed', null, metadata: [
                'filename' => basename($filename), 'products_created' => $products, 'variants_created' => $variants,
            ]);

            return compact('products', 'variants');
        });
    }

    /** @param array<int, ProductImportRowData> $rows
     * @return array<int, array<int, ProductImportRowData>>
     */
    private function groups(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->productRef][] = $row;
        }

        return array_values($groups);
    }

    private function createProduct(ProductImportRowData $row, ?int $supplierId): Product
    {
        return $this->createProduct->execute(new CreateProductData(
            brandId: $row->brandId,
            categoryId: $row->categoryId,
            nameAr: $row->nameAr,
            nameEn: $row->nameEn,
            slug: $this->slugGenerator->generate($row->nameEn ?? $row->nameAr, Product::class),
            descriptionAr: $row->descriptionAr,
            descriptionEn: null,
            status: $row->isActive ? ProductStatus::Active : ProductStatus::Inactive,
            hasVariants: true,
            supplierId: $supplierId,
        ));
    }
}
