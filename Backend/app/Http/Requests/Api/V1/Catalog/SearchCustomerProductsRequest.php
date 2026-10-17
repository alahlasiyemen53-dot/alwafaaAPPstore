<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class SearchCustomerProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q') && is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }

        if ($this->has('per_page') && is_numeric($this->input('per_page'))) {
            $perPage = (int) $this->input('per_page');
            if ($perPage > 50) {
                $this->merge(['per_page' => 50]);
            }
        }

        $this->merge([
            'category_id' => $this->normalizeIdList($this->input('category_id')),
            'brand_id' => $this->normalizeIdList($this->input('brand_id')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'category_id' => ['nullable', 'array', 'max:50'],
            'category_id.*' => ['integer', 'min:1'],
            'brand_id' => ['nullable', 'array', 'max:50'],
            'brand_id.*' => ['integer', 'min:1'],
            'badge' => ['nullable', 'string', 'in:best_seller,new,exclusive'],
            'availability' => ['nullable', 'string', 'in:in_stock,on_demand,any_available,stocked'],
            'sort' => ['nullable', 'string', 'in:relevance,featured,price_asc,price_desc,rating,newest'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'حقل كلمة البحث مطلوب.',
            'q.min' => 'يجب إدخال حرف واحد على الأقل للبحث.',
            'q.max' => 'كلمة البحث لا يمكن أن تتجاوز 100 حرف.',
            'category_id.array' => 'معرفات الأقسام يجب أن تكون قائمة.',
            'category_id.max' => 'لا يمكن اختيار أكثر من 50 قسماً.',
            'category_id.*.integer' => 'معرف القسم يجب أن يكون رقماً صحيحاً.',
            'category_id.*.min' => 'معرف القسم غير صالح.',
            'brand_id.array' => 'معرفات العلامات التجارية يجب أن تكون قائمة.',
            'brand_id.max' => 'لا يمكن اختيار أكثر من 50 علامة تجارية.',
            'brand_id.*.integer' => 'معرف العلامة التجارية يجب أن يكون رقماً صحيحاً.',
            'brand_id.*.min' => 'معرف العلامة التجارية غير صالح.',
            'badge.in' => 'شارة التمييز المختارة غير صالحة. القيم المتاحة: best_seller, new, exclusive.',
            'availability.in' => 'حالة التوفر المختارة غير صالحة.',
            'sort.in' => 'معيار الترتيب غير صالح. القيم المتاحة: relevance, featured, price_asc, price_desc, rating, newest.',
            'per_page.max' => 'الحد الأقصى لعدد العناصر في الصفحة هو 50 منتجاً.',
        ];
    }

    /**
     * @return list<int>|null
     */
    private function normalizeIdList(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($items as $item) {
            if (is_numeric($item) && (int) $item >= 1) {
                $ids[] = (int) $item;
            }
        }

        $ids = array_values(array_unique($ids));

        return $ids === [] ? null : $ids;
    }
}
