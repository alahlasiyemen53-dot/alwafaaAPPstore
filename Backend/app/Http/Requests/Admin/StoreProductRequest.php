<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Rules\ActiveSupplierRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('brand_id')) {
            $brandId = $this->input('brand_id');
            $merge['brand_id'] = ($brandId === '' || $brandId === null) ? null : (int) $brandId;
        }
        if ($this->has('supplier_id')) {
            $supplierId = $this->input('supplier_id');
            $merge['supplier_id'] = ($supplierId === '' || $supplierId === null) ? null : (int) $supplierId;
        }
        if ($this->has('supplier_item_number')) {
            $itemNo = trim((string) $this->input('supplier_item_number'));
            $merge['supplier_item_number'] = $itemNo === '' ? null : $itemNo;
        }
        if ($this->has('name_ar')) {
            $merge['name_ar'] = trim((string) $this->input('name_ar'));
        }
        if ($this->has('name_en')) {
            $nameEn = trim((string) $this->input('name_en'));
            $merge['name_en'] = $nameEn === '' ? null : $nameEn;
        }
        if (! $this->filled('slug')) {
            $slugGen = app(\App\Services\SlugGenerator::class);
            $name = $merge['name_en'] ?? $this->input('name_en') ?: ($merge['name_ar'] ?? $this->input('name_ar') ?: 'product');
            $merge['slug'] = $slugGen->generate((string) $name, \App\Models\Product::class);
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }

        $this->normalizeVariants();
    }

    private function normalizeVariants(): void
    {
        if (! $this->exists('variants') || ! is_array($this->input('variants'))) {
            return;
        }

        $variants = [];
        foreach ($this->input('variants') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attributeIds = array_values(array_filter(
                (array) ($row['attribute_value_ids'] ?? []),
                fn ($value) => $value !== null && $value !== '' && is_numeric($value) && (int) $value > 0
            ));

            $barcode = trim((string) ($row['barcode'] ?? ''));
            $compare = trim((string) ($row['compare_at_price'] ?? ''));
            $itemNo = trim((string) ($row['supplier_item_number'] ?? ''));
            $supplierId = $row['supplier_id'] ?? null;

            $variants[] = [
                'price' => $row['price'] ?? null,
                'compare_at_price' => $compare === '' ? null : $compare,
                'barcode' => $barcode === '' ? null : $barcode,
                'supplier_item_number' => $itemNo === '' ? null : $itemNo,
                'fulfillment_mode' => ($row['fulfillment_mode'] ?? '') !== '' ? $row['fulfillment_mode'] : 'stocked',
                'supplier_id' => ($supplierId === '' || $supplierId === null) ? null : (int) $supplierId,
                'status' => ($row['status'] ?? '') !== '' ? $row['status'] : 'active',
                'is_default' => filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'attribute_value_ids' => $attributeIds,
            ];
        }

        $this->merge(['variants' => $variants]);
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'name_ar' => ['required', 'string', 'max:255', Rule::unique('products', 'name_ar')],
            'name_en' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'has_variants' => ['sometimes', 'boolean'],
            'is_best_seller' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'is_exclusive' => ['sometimes', 'boolean'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id'), new ActiveSupplierRule],
            'supplier_item_number' => ['nullable', 'string', 'max:100'],
            'variants' => ['nullable', 'array'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64', 'distinct', Rule::unique('product_variants', 'barcode')],
            'variants.*.supplier_item_number' => ['nullable', 'string', 'max:100'],
            'variants.*.fulfillment_mode' => ['nullable', Rule::in(['stocked', 'on_demand'])],
            'variants.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'variants.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attribute_value_ids' => ['nullable', 'array'],
            'variants.*.attribute_value_ids.*' => ['integer', Rule::exists('attribute_values', 'id')],
            'variants.*.image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'primary_image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'brand_id' => 'العلامة التجارية',
            'category_id' => 'التصنيف',
            'name_ar' => 'الاسم العربي',
            'name_en' => 'الاسم الإنجليزي',
            'slug' => 'المعرّف النصي',
            'status' => 'الحالة',
            'has_variants' => 'خيارات متعددة',
            'is_best_seller' => 'الأكثر مبيعاً',
            'is_new' => 'منتج جديد',
            'is_exclusive' => 'منتج حصري',
            'supplier_id' => 'المورد',
            'supplier_item_number' => 'رقم الصنف لدى المورد',
            'description_ar' => 'الوصف العربي',
            'description_en' => 'الوصف الإنجليزي',
            'variants.*.price' => 'سعر المتغير',
            'variants.*.compare_at_price' => 'سعر المقارنة',
            'variants.*.barcode' => 'الباركود',
            'variants.*.supplier_id' => 'مورد المتغير',
            'variants.*.attribute_value_ids.*' => 'خاصية المتغير',
            'variants.*.image' => 'صورة المتغير',
            'primary_image' => 'الصورة الرئيسية',
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required' => 'الاسم العربي للمنتج مطلوب.',
            'name_ar.unique' => 'يوجد منتج بنفس الاسم العربي مسبقاً.',
            'brand_id.required' => 'يرجى اختيار العلامة التجارية.',
            'brand_id.exists' => 'العلامة التجارية المختارة غير صالحة أو غير نشطة.',
            'category_id.required' => 'يرجى اختيار تصنيف للمنتج.',
            'category_id.exists' => 'التصنيف المختار غير صالح أو غير نشط.',
            'slug.required' => 'المعرّف النصي (Slug) مطلوب.',
            'slug.unique' => 'المعرّف النصي مستخدم بالفعل لمنتج آخر.',
            'slug.regex' => 'المعرّف النصي يجب أن يحتوي على حروف إنجليزية صغيرة وأرقام وشرطات فقط.',
            'status.required' => 'حالة المنتج مطلوبة.',
            'variants.*.price.required' => 'سعر المتغير مطلوب.',
            'variants.*.compare_at_price.numeric' => 'سعر المقارنة يجب أن يكون رقماً.',
            'variants.*.barcode.unique' => 'الباركود مستخدم بالفعل.',
            'variants.*.barcode.distinct' => 'لا يمكن تكرار الباركود بين المتغيرات.',
            'variants.*.supplier_id.exists' => 'المورد المحدد للمتغير غير موجود.',
            'variants.*.image.image' => 'صورة المتغير يجب أن تكون ملفاً صورياً.',
            'variants.*.image.mimes' => 'صورة المتغير يجب أن تكون بصيغة JPG أو PNG أو WEBP.',
            'variants.*.image.max' => 'حجم صورة المتغير يجب ألا يتجاوز 5 ميغابايت.',
            'primary_image.image' => 'الصورة الرئيسية يجب أن تكون ملفاً صورياً.',
            'primary_image.mimes' => 'الصورة الرئيسية يجب أن تكون بصيغة JPG أو PNG أو WEBP.',
            'primary_image.max' => 'حجم الصورة الرئيسية يجب ألا يتجاوز 5 ميغابايت.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ((array) $this->input('variants', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $price = $row['price'] ?? null;
                $compare = $row['compare_at_price'] ?? null;
                if ($compare !== null && $compare !== '' && is_numeric($price) && is_numeric($compare) && (float) $compare <= (float) $price) {
                    $validator->errors()->add("variants.$index.compare_at_price", 'سعر المقارنة يجب أن يكون أعلى من السعر الأساسي.');
                }
                if (($row['fulfillment_mode'] ?? null) === 'on_demand' && empty($row['supplier_id'])) {
                    $validator->errors()->add("variants.$index.supplier_id", 'يجب اختيار المورد عند تحديد التوفير عند الطلب.');
                }
            }
        });
    }
}
