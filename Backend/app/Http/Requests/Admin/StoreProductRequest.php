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
        ];
    }
}
