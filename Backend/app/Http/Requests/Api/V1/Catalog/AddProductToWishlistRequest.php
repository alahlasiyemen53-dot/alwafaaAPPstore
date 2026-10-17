<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalog;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class AddProductToWishlistRequest extends FormRequest
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
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'معرف المنتج مطلوب لإضافته للمفضلة',
            'product_id.integer' => 'معرف المنتج يجب أن يكون عدداً صحيحاً',
            'product_id.exists' => 'المنتج المحدد غير موجود في المتجر',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'بيانات إضافة المنتج للمفضلة غير صالحة',
                'code' => ApiErrorCode::ValidationFailed->value,
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
