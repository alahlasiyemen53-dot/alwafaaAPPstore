<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entity = $this->route('entity') ?? $this->segment(4);
        $id = $this->route('masterData');
        $unique = fn (string $table, string $column = 'id') => Rule::unique($table, $column)->ignore($id);

        return match ($entity) {
            'countries' => [
                'code' => [$id ? 'prohibited' : 'required', 'string', 'size:2', 'alpha', $unique('countries', 'code')],
                'name_ar' => 'required|string|max:255',
                'name_en' => 'nullable|string|max:255',
                'calling_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,4}$/'],
                'sort_order' => 'required|integer|min:0',
                'is_active' => 'boolean',
            ],
            'currencies' => ['code' => ['nullable', 'string', 'size:3', $unique('currencies', 'code')], 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'symbol' => 'nullable|string|max:8', 'decimal_places' => 'integer|min:0|max:8', 'is_default' => 'boolean', 'is_active' => 'boolean'],
            'units' => ['code' => ['nullable', 'string', 'max:32', $unique('units', 'code')], 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'decimal_places' => 'integer|min:0|max:8', 'is_active' => 'boolean'],
            'brands' => ['name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'slug' => ['nullable', 'string', 'max:255', $unique('brands', 'slug')], 'description' => 'nullable|string', 'is_active' => 'boolean'],
            'governorates' => ['name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'slug' => ['nullable', 'string', 'max:255', $unique('governorates', 'slug')], 'is_active' => 'boolean'],
            'cities' => ['governorate_id' => 'required|exists:governorates,id', 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'slug' => 'nullable|string|max:255', 'is_active' => 'boolean'],
            'districts' => ['city_id' => 'required|exists:cities,id', 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'slug' => 'nullable|string|max:255', 'is_active' => 'boolean'],
            'categories' => ['parent_id' => 'nullable|exists:categories,id', 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'slug' => ['nullable', 'string', 'max:255', $unique('categories', 'slug')], 'sort_order' => 'integer|min:0', 'is_active' => 'boolean'],
            'warehouses' => ['code' => ['nullable', 'string', 'max:32', $unique('warehouses', 'code')], 'name_ar' => 'required|string|max:255', 'name_en' => 'nullable|string|max:255', 'governorate_id' => 'nullable|exists:governorates,id', 'city_id' => 'nullable|exists:cities,id', 'district_id' => 'nullable|exists:districts,id', 'address_text' => 'nullable|string', 'phone' => 'nullable|string|max:32', 'is_active' => 'boolean'],
            'delivery-methods' => [
                'code' => ['nullable', 'string', 'max:64', 'alpha_dash', $unique('delivery_methods', 'code')],
                'name_ar' => 'required|string|max:255',
                'name_en' => 'nullable|string|max:255',
                'price' => ['required', 'numeric', 'min:0'],
                'estimated_delivery_ar' => 'nullable|string|max:255',
                'estimated_delivery_en' => 'nullable|string|max:255',
                'sort_order' => 'required|integer|min:0',
                'is_active' => 'boolean',
            ],
            default => [],
        };
    }
}
