<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ConfirmCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp' => [
                'required',
                'string',
                'max:12',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.max' => 'رمز التحقق لا يمكن أن يتجاوز 12 حرفاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات تأكيد الدفع غير صالحة.'
            )
        );
    }
}
