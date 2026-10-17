<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Country;
use App\Models\Customer;
use App\Support\CountryCallingCode;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyCustomerOtpRequest extends FormRequest
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
        $phone = (string) $this->input('phone');
        $countryId = $this->integer('country_id') ?: null;
        $callingCode = CountryCallingCode::forCountryId($countryId);
        $isNewCustomer = true;
        if (PhoneNormalizer::isValid($phone, $countryId)) {
            $normalizedPhone = PhoneNormalizer::normalize($phone, $callingCode);
            $isNewCustomer = ! Customer::query()->where('normalized_phone', $normalizedPhone)->exists();
        }

        $countryIdForRules = $this->input('country_id');

        return [
            'country_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('countries', 'id')->where('is_active', true),
            ],
            'governorate_id' => [
                $isNewCustomer ? 'required' : 'sometimes',
                'nullable',
                'integer',
                Rule::exists('governorates', 'id')
                    ->where('is_active', true)
                    ->when($countryIdForRules, fn ($rule) => $rule->where('country_id', $countryIdForRules)),
            ],
            'phone' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail) use ($countryId): void {
                    if (! PhoneNormalizer::isValid((string) $value, $countryId)) {
                        $fail('رقم الهاتف المدخل غير صالح.');
                    }
                },
            ],
            'challenge_id' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:4', 'max:10'],
            'name' => [$isNewCustomer ? 'required' : 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب.',
            'challenge_id.required' => 'معرف طلب التحقق مطلوب.',
            'challenge_id.uuid' => 'صيغة معرف طلب التحقق غير صالحة.',
            'code.required' => 'رمز التحقق مطلوب.',
            'code.regex' => 'رمز التحقق يجب أن يتكون من أرقام فقط.',
            'name.required' => 'الاسم الكامل إجباري عند تسجيل حساب جديد.',
            'governorate_id.required' => 'المحافظة مطلوبة عند تسجيل حساب جديد.',
            'country_id.exists' => 'الدولة المحددة غير متاحة حالياً. اختر دولة نشطة ثم أعد المحاولة.',
        ];
    }
}
