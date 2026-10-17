<?php

namespace App\Actions;

use App\Data\UpdateProductData;
use App\Enums\SourcingStatus;
use App\Models\OrderSourcingRequirement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\ProductRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProductAction
{
    private const AUDITED_FIELDS = [
        'brand_id', 'category_id', 'name_ar', 'name_en', 'slug',
        'description_ar', 'description_en', 'has_variants',
        'is_best_seller', 'is_new', 'is_exclusive', 'supplier_id', 'supplier_item_number',
    ];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product, UpdateProductData $productData): Product
    {
        return DB::transaction(function () use ($product, $productData): Product {
            $supplierChanged = (int) ($product->supplier_id ?? 0) !== (int) ($productData->supplierId ?? 0);

            if ($supplierChanged) {
                $this->guardSupplierChange($product, $productData);
            }

            $before = Arr::only($product->getAttributes(), self::AUDITED_FIELDS);
            $updated = $this->products->update($product, $productData);

            $clearedItemNumbers = false;
            if ($supplierChanged && $productData->confirmSupplierChange) {
                $clearedItemNumbers = $this->clearSupplierItemNumbers($updated);
                $updated = $updated->fresh(['brand', 'category']);
            }

            $after = Arr::only($updated->getAttributes(), self::AUDITED_FIELDS);
            $this->auditLogger->record('admin.product.updated', $updated, metadata: compact('before', 'after'));

            if ($clearedItemNumbers) {
                $updated->setAttribute('supplier_item_numbers_cleared', true);
            }

            return $updated;
        });
    }

    private function guardSupplierChange(Product $product, UpdateProductData $productData): void
    {
        $hasVariantItemNumbers = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNotNull('supplier_item_number')
            ->where('supplier_item_number', '!=', '')
            ->exists();

        $hasProductItemNumber = filled($product->supplier_item_number);

        $hasActiveSourcing = OrderSourcingRequirement::query()
            ->whereIn('status', [SourcingStatus::Pending, SourcingStatus::Assigned])
            ->whereHas('productVariant', fn ($query) => $query->where('product_id', $product->id))
            ->exists();

        if (($hasVariantItemNumbers || $hasProductItemNumber || $hasActiveSourcing) && ! $productData->confirmSupplierChange) {
            throw ValidationException::withMessages([
                'supplier_id' => ['تغيير المورد يتطلب تأكيداً صريحاً لأن المنتج يحتوي على أرقام صنف لدى المورد أو مهام توفير نشطة. أرسل confirm_supplier_change=1 للمتابعة.'],
            ]);
        }
    }

    private function clearSupplierItemNumbers(Product $product): bool
    {
        $productCleared = filled($product->supplier_item_number);
        if ($productCleared) {
            $product->forceFill(['supplier_item_number' => null])->save();
        }

        $variantsCleared = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNotNull('supplier_item_number')
            ->where('supplier_item_number', '!=', '')
            ->update(['supplier_item_number' => null]);

        return $productCleared || $variantsCleared > 0;
    }
}
