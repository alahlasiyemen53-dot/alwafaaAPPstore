<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Country;
use App\Support\ApiResponse;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('country_id')) {
            $defaultCountryId = Country::defaultDeliveryActive()?->id ?? Country::defaultActive()?->id;
            if ($defaultCountryId !== null) {
                $this->merge(['country_id' => $defaultCountryId]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryId = $this->input('country_id');

        return [
            'label' => [
                'required',
                'string',
                'max:64',
            ],
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->where('is_active', true)->where('is_delivery_active', true),
            ],
            'formatted_address' => [
                'required_without:street',
                'nullable',
                'string',
                'max:500',
            ],
            'street' => [
                'required_without:formatted_address',
                'nullable',
                'string',
                'max:255',
            ],
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'id')
                    ->where('is_active', true)
                    ->where('is_delivery_active', true)
                    ->when($countryId, fn ($rule) => $rule->where('country_id', $countryId)),
            ],
            'city_id' => ['prohibited'],
            'district_id' => ['prohibited'],
            'neighborhood' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'building' => [
                'sometimes',
                'nullable',
                'string',
                'max:128',
            ],
            'floor' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],
            'apartment' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],
            'landmark' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'contact_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'contact_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && $value !== '' && ! PhoneNormalizer::isValid((string) $value, $this->integer('country_id') ?: null)) {
                        $fail('رقم هاتف المستلم غير صالح.');
                    }
                },
            ],
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'is_default' => [
                'sometimes',
                'boolean',
            ],
            'customer_id' => [
                'prohibited',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'تسمية العنوان مطلوبة (مثل: المنزل، العمل).',
            'country_id.required' => 'الدولة مطلوبة.',
            'country_id.exists' => 'الدولة المحددة غير متاحة للتوصيل حالياً.',
            'formatted_address.required' => 'يرجى كتابة العنوان بوضوح.',
            'formatted_address.required_without' => 'يرجى كتابة العنوان بوضوح.',
            'street.required_without' => 'يرجى كتابة العنوان بوضوح.',
            'governorate_id.required' => 'المحافظة مطلوبة.',
            'governorate_id.exists' => 'المحافظة المحددة غير متاحة للتوصيل أو لا تنتمي إلى الدولة المختارة.',
            'latitude.required' => 'يرجى تحديد موقع العنوان على خريطة Google.',
            'longitude.required' => 'يرجى تحديد موقع العنوان على خريطة Google.',
            'customer_id.prohibited' => 'لا يمكن تمرير معرف العميل يدوياً؛ يتم التعيين تلقائياً بحسب جلسة المستخدم.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات إضافة العنوان غير صالحة.'
            )
        );
    }
}
