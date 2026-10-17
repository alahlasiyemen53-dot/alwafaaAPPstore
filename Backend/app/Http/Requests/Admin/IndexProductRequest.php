<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'sort' => ['nullable', Rule::in(['name_ar', 'name_en', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'best_seller' => ['nullable', 'string', Rule::in(['all', 'yes', 'no', '1', '0'])],
            'new' => ['nullable', 'string', Rule::in(['all', 'yes', 'no', '1', '0'])],
            'exclusive' => ['nullable', 'string', Rule::in(['all', 'yes', 'no', '1', '0'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
