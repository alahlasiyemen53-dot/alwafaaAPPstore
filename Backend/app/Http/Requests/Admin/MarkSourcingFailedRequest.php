<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class MarkSourcingFailedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'failure_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'failure_reason' => 'سبب تعذر التوريد',
        ];
    }

    public function messages(): array
    {
        return [
            'failure_reason.required' => 'يجب إدخال سبب تعذر استلام البند من المورد.',
            'failure_reason.max' => 'يجب ألا يتجاوز سبب التعذر 500 حرف.',
        ];
    }
}
