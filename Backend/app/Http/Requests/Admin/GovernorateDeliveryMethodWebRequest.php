<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GovernorateDeliveryMethodWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assignmentId = $this->route('assignment')?->id ?? $this->route('assignment');

        return [
            'governorate_id' => ['required', 'exists:governorates,id'],
            'delivery_method_id' => [
                'required',
                'exists:delivery_methods,id',
                Rule::unique('governorate_delivery_methods', 'delivery_method_id')
                    ->where(fn ($query) => $query->where('governorate_id', $this->input('governorate_id')))
                    ->ignore($assignmentId),
            ],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
