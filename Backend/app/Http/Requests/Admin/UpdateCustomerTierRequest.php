<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.customer_tiers.update') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tier = $this->route('tier');
        $tierId = is_object($tier) ? $tier->id : $tier;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[A-Z0-9_\-]+$/i', Rule::unique('customer_tiers', 'code')->ignore($tierId)],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'كود المستوى مطلوب.',
            'code.unique' => 'كود المستوى مستخدم بالفعل.',
            'code.regex' => 'كود المستوى يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطات فقط.',
            'name_ar.required' => 'اسم المستوى باللغة العربية مطلوب.',
            'name_ar.max' => 'اسم المستوى يجب ألا يتجاوز 100 حرف.',
            'sort_order.integer' => 'ترتيب العرض يجب أن يكون رقماً صحيحاً.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
            'sort_order' => $this->input('sort_order') !== null ? (int) $this->input('sort_order') : 0,
        ]);
    }
}
