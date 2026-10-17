<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Cart;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class ApplyCouponRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:50',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'يرجى إدخال كود الكوبون.',
            'code.string' => 'كود الكوبون يجب أن يكون نصاً صالحاً.',
            'code.max' => 'كود الكوبون لا يمكن أن يتجاوز 50 حرفاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            \App\Support\ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات كود الكوبون غير صالحة.'
            )
        );
    }
}
