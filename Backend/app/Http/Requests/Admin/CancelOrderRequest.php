<?php

namespace App\Http\Requests\Admin;

use App\DTOs\CancelOrderData;
use Illuminate\Foundation\Http\FormRequest;

final class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'يرجى كتابة سبب إلغاء الطلب.',
            'cancellation_reason.min' => 'يجب ألا يقل سبب الإلغاء عن 3 أحرف.',
            'cancellation_reason.max' => 'يجب ألا يتجاوز سبب الإلغاء 1000 حرف.',
        ];
    }

    public function toDto(): CancelOrderData
    {
        return CancelOrderData::fromArray($this->validated());
    }
}
