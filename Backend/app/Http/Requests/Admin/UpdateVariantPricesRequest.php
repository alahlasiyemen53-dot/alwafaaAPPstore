<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateVariantPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('compare_at_price')) {
            $compare = trim((string) $this->input('compare_at_price'));
            $this->merge([
                'compare_at_price' => $compare === '' ? null : $compare,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'price' => 'السعر بالعملة الأساسية',
            'compare_at_price' => 'سعر المقارنة',
            'is_active' => 'حالة التفعيل',
        ];
    }

    public function messages(): array
    {
        return [
            'price.required' => 'السعر بالعملة الأساسية مطلوب.',
            'price.numeric' => 'يجب أن يكون السعر رقماً صحيحاً أو عشرياً.',
            'price.min' => 'يجب ألا يقل السعر عن 0.',
            'compare_at_price.numeric' => 'يجب أن يكون سعر المقارنة رقماً.',
            'compare_at_price.min' => 'يجب ألا يقل سعر المقارنة عن 0.',
            'compare_at_price.gt' => 'سعر المقارنة يجب أن يكون أعلى من السعر الأساسي.',
        ];
    }
}
