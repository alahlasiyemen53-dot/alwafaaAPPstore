<?php

namespace App\Services;

use App\Data\ProductImportRowData;
use App\Enums\ProductVariantFulfillmentMode;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Str;

final class ProductImportValidationService
{
    /** @param array<int, array<string, string>> $rawRows
     * @return array{rows: array<int, ProductImportRowData>, errors: array<int, array<int, string>>, warnings: array<int, string>, products: int}
     */
    public function validate(array $rawRows): array
    {
        $lookups = $this->lookups();
        $rows = [];
        $errors = [];
        $warnings = [];
        $seenVariants = [];
        $productDefinitions = [];

        foreach ($rawRows as $rowNumber => $rawRow) {
            $rowErrors = $this->rowErrors($rowNumber, $rawRow, $lookups, $seenVariants, $productDefinitions);
            if ($rowErrors !== []) {
                $errors[$rowNumber] = $rowErrors;

                continue;
            }
            $rows[$rowNumber] = $this->toRow($rowNumber, $rawRow, $lookups);
            if ($rawRow['notes'] !== '') {
                $warnings[$rowNumber] = 'حقل الملاحظات لا يغير بيانات المنتج ولم يُستورد.';
            }
        }

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings, 'products' => count($productDefinitions)];
    }

    /** @return array<string, array<string, int>> */
    private function lookups(): array
    {
        $normal = fn (string $value): string => mb_strtolower(Str::squish($value));
        $brands = [];
        foreach (Brand::query()->where('is_active', true)->get() as $brand) {
            $brands[$normal($brand->name_ar)] = $brand->id;
            if ($brand->name_en) {
                $brands[$normal($brand->name_en)] = $brand->id;
            }
        }
        $categories = [];
        foreach (Category::query()->where('is_active', true)->get() as $category) {
            $categories[$normal($category->name_ar)] = $category->id;
            if ($category->name_en) {
                $categories[$normal($category->name_en)] = $category->id;
            }
        }
        $attributeValues = AttributeValue::query()->with('attribute')->where('is_active', true)->get()
            ->filter(fn (AttributeValue $value) => $value->attribute?->is_active)
            ->mapWithKeys(fn (AttributeValue $value) => [$value->attribute->code.'|'.mb_strtolower($value->code) => $value->id])->all();
        $suppliers = Supplier::query()->where('is_active', true)->get()->mapWithKeys(fn (Supplier $supplier) => [
            $normal($supplier->code) => $supplier->id,
            $normal($supplier->name) => $supplier->id,
        ])->all();
        $warehouses = Warehouse::query()->where('is_active', true)->get()->mapWithKeys(fn (Warehouse $warehouse) => [
            $normal($warehouse->code) => $warehouse->id,
            $normal($warehouse->name_ar) => $warehouse->id,
        ])->all();

        return compact('brands', 'categories', 'attributeValues', 'suppliers', 'warehouses');
    }

    /** @param array<string, string> $row
     * @param  array<string, array<string, int>>  $lookups
     * @param  array<string, int>  $seenVariants
     * @param  array<string, string>  $productDefinitions
     * @return array<int, string>
     */
    private function rowErrors(int $rowNumber, array $row, array $lookups, array &$seenVariants, array &$productDefinitions): array
    {
        $errors = [];
        foreach (['product_ref', 'name_ar', 'category', 'variant_ref', 'price', 'availability_type', 'is_active'] as $field) {
            if ($row[$field] === '') {
                $errors[] = "الحقل {$field} مطلوب.";
            }
        }
        if (mb_strlen($row['name_ar']) > 255 || mb_strlen($row['name_en']) > 255 || mb_strlen($row['description_ar']) > 65535) {
            $errors[] = 'أحد الحقول النصية أطول من الحد المسموح.';
        }
        if (! is_numeric($row['price']) || (float) $row['price'] < 0) {
            $errors[] = 'السعر يجب أن يكون رقماً موجباً أو صفراً.';
        }
        if (! isset($lookups['categories'][$this->key($row['category'])])) {
            $errors[] = 'التصنيف غير موجود أو غير نشط.';
        }
        if ($row['brand'] !== '' && ! isset($lookups['brands'][$this->key($row['brand'])])) {
            $errors[] = 'العلامة التجارية غير موجودة أو غير نشطة.';
        }
        foreach (['size', 'shade'] as $attribute) {
            if ($row[$attribute] !== '' && ! isset($lookups['attributeValues'][$attribute.'|'.mb_strtolower($row[$attribute])])) {
                $errors[] = "قيمة {$attribute} غير موجودة أو غير نشطة؛ استخدم code القيمة المعتمد.";
            }
        }
        $mode = ProductVariantFulfillmentMode::tryFrom(mb_strtolower($row['availability_type']));
        if (! $mode) {
            $errors[] = 'availability_type يجب أن يكون stocked أو on_demand.';
        }
        if ($row['supplier'] !== '' && ! isset($lookups['suppliers'][$this->key($row['supplier'])])) {
            $errors[] = 'المورد غير موجود أو غير نشط (استخدم الاسم أو code).';
        }
        if ($mode === ProductVariantFulfillmentMode::OnDemand && $row['supplier'] === '') {
            $errors[] = 'التوفير عند الطلب يتطلب تحديد المورد.';
        }
        if ($row['supplier_item_number'] !== '' && mb_strlen($row['supplier_item_number']) > 100) {
            $errors[] = 'supplier_item_number أطول من 100 حرف.';
        }
        if ($mode === ProductVariantFulfillmentMode::OnDemand && $row['stock_quantity'] !== '') {
            $errors[] = 'التوفير عند الطلب لا يقبل كمية مخزون.';
        }
        if ($mode === ProductVariantFulfillmentMode::Stocked && $row['stock_quantity'] !== '') {
            if (! is_numeric($row['stock_quantity']) || (float) $row['stock_quantity'] < 0) {
                $errors[] = 'كمية المخزون يجب أن تكون رقماً موجباً أو صفراً.';
            } elseif ((float) $row['stock_quantity'] > 0 && ! isset($lookups['warehouses'][$this->key($row['warehouse'])])) {
                $errors[] = 'كمية مخزون موجبة تتطلب مستودعاً نشطاً بالاسم أو code.';
            }
        }
        if (! in_array(mb_strtolower($row['is_active']), ['1', '0', 'true', 'false', 'yes', 'no', 'نعم', 'لا'], true)) {
            $errors[] = 'is_active يجب أن يكون 1/0 أو true/false أو نعم/لا.';
        }
        $variantKey = $row['product_ref'].'|'.$row['variant_ref'];
        if (isset($seenVariants[$variantKey])) {
            $errors[] = "تكرار product_ref + variant_ref مع الصف {$seenVariants[$variantKey]}.";
        } else {
            $seenVariants[$variantKey] = $rowNumber;
        }
        $definition = implode('|', [$row['name_ar'], $row['name_en'], $row['brand'], $row['category'], $row['description_ar'], $row['is_active']]);
        if (isset($productDefinitions[$row['product_ref']]) && $productDefinitions[$row['product_ref']] !== $definition) {
            $errors[] = 'صفوف product_ref نفسه يجب أن تحمل بيانات منتج متطابقة.';
        } elseif ($errors === []) {
            $productDefinitions[$row['product_ref']] = $definition;
        }

        return $errors;
    }

    /** @param array<string, string> $row
     * @param  array<string, array<string, int>>  $lookups
     */
    private function toRow(int $rowNumber, array $row, array $lookups): ProductImportRowData
    {
        $attributeValueIds = [];
        foreach (['size', 'shade'] as $attribute) {
            if ($row[$attribute] !== '') {
                $attributeValueIds[] = $lookups['attributeValues'][$attribute.'|'.mb_strtolower($row[$attribute])];
            }
        }
        $mode = ProductVariantFulfillmentMode::from(mb_strtolower($row['availability_type']));
        $supplierId = $row['supplier'] === '' ? null : $lookups['suppliers'][$this->key($row['supplier'])];
        $supplierItemNumber = $row['supplier_item_number'] === '' ? null : trim($row['supplier_item_number']);

        return new ProductImportRowData(
            $rowNumber, $row['product_ref'], $row['variant_ref'], $row['name_ar'], $row['name_en'] ?: null,
            $row['description_ar'] ?: null, $row['brand'] === '' ? null : $lookups['brands'][$this->key($row['brand'])],
            $lookups['categories'][$this->key($row['category'])], $attributeValueIds, number_format((float) $row['price'], 4, '.', ''),
            $mode, $supplierId, $supplierItemNumber,
            $row['warehouse'] === '' ? null : ($lookups['warehouses'][$this->key($row['warehouse'])] ?? null),
            $row['stock_quantity'] === '' ? null : number_format((float) $row['stock_quantity'], 4, '.', ''),
            in_array(mb_strtolower($row['is_active']), ['1', 'true', 'yes', 'نعم'], true),
        );
    }

    private function key(string $value): string
    {
        return mb_strtolower(Str::squish($value));
    }
}
