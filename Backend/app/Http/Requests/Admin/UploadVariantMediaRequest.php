<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UploadVariantMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'is_primary' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
