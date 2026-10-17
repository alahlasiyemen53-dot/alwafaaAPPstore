<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class AddCartItemRequest extends FormRequest
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
            'product_variant_id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],
            'quantity' => [
                'sometimes',
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
            'product_variant_id.required' => 'معرف نوع المنتج مطلوب للإضافة إلى السلة.',
            'product_variant_id.integer' => 'معرف نوع المنتج يجب أن يكون عدداً صحيحاً.',
            'product_variant_id.exists' => 'نوع المنتج المحدد غير موجود.',
            'quantity.integer' => 'الكمية يجب أن تكون عدداً صحيحاً.',
            'quantity.min' => 'يجب إضافة قطعة واحدة على الأقل.',
            'quantity.max' => 'الحد الأقصى للكمية المسموح بها لكل عنصر هو 100 قطعة.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            \App\Support\ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات إضافة العنصر إلى السلة غير صالحة.'
            )
        );
    }
}
