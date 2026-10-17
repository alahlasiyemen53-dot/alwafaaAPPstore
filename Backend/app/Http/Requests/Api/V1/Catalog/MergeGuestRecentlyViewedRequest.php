<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class MergeGuestRecentlyViewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'max:20'],
            'product_ids.*' => ['integer', 'min:1'],
        ];
    }
}
