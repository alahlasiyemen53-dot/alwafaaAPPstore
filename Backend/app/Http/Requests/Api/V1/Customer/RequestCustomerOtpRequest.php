<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Country;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestCustomerOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('country_id')) {
            $defaultCountryId = Country::defaultActive()?->id;
            if ($defaultCountryId !== null) {
                $this->merge(['country_id' => $defaultCountryId]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'country_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('countries', 'id')->where('is_active', true),
            ],
            'phone' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! PhoneNormalizer::isValid((string) $value, $this->integer('country_id') ?: null)) {
                        $fail('رقم الهاتف المدخل غير صالح.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.string' => 'صيغة رقم الهاتف غير صالحة.',
            'phone.max' => 'رقم الهاتف لا يجب أن يتجاوز 32 حرفاً.',
            'country_id.exists' => 'الدولة المحددة غير متاحة حالياً. اختر دولة نشطة ثم أعد المحاولة.',
        ];
    }
}
