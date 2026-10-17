<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'الكمية الجديدة مطلوبة لتحديث عنصر السلة.',
            'quantity.integer' => 'الكمية يجب أن تكون عدداً صحيحاً.',
            'quantity.min' => 'الكمية يجب ألا تقل عن 1.',
            'quantity.max' => 'الحد الأقصى للكمية المسموح بها لكل عنصر هو 100 قطعة.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            \App\Support\ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات تحديث كمية السلة غير صالحة.'
            )
        );
    }
}
