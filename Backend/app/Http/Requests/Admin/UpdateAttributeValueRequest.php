<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attribute = $this->route('attribute');
        $attributeId = is_object($attribute) ? $attribute->id : $attribute;
        $value = $this->route('value');
        $valueId = is_object($value) ? $value->id : $value;

        return [
            'value_ar' => ['required', 'string', 'max:255'],
            'value_en' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('attribute_values', 'code')
                    ->where('attribute_id', $attributeId)
                    ->ignore($valueId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
