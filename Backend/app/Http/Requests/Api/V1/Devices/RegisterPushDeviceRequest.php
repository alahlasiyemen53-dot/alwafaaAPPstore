<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Devices;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class RegisterPushDeviceRequest extends FormRequest
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
            'platform' => [
                'required',
                'string',
                Rule::in(['android', 'ios']),
            ],
            'fcm_token' => [
                'required',
                'string',
                'min:10',
                'max:2048',
            ],
            'device_identifier' => [
                'sometimes',
                'nullable',
                'string',
                'max:191',
            ],
            'locale' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],
            'app_version' => [
                'sometimes',
                'nullable',
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
            'platform.required' => 'نوع منصة الجهاز مطلوب (android أو ios).',
            'platform.in' => 'منصة الجهاز المحددة غير مدعومة. المنصات المدعومة: android, ios.',
            'fcm_token.required' => 'رمز الإشعار (FCM token) مطلوب.',
            'fcm_token.min' => 'رمز الإشعار المدخل قصير للغاية وغير صالح.',
            'fcm_token.max' => 'رمز الإشعار المدخل يتجاوز الحد الأقصى المسموح به.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات تسجيل الجهاز غير صالحة.'
            )
        );
    }
}
