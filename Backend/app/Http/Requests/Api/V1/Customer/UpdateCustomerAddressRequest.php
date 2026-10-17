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

final class UpdateCustomerAddressRequest extends FormRequest
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
                'sometimes',
                'string',
                'max:64',
            ],
            'country_id' => [
                'sometimes',
                'integer',
                Rule::exists('countries', 'id')->where('is_active', true)->where('is_delivery_active', true),
            ],
            'governorate_id' => [
                'sometimes',
                'integer',
                Rule::exists('governorates', 'id')
                    ->where('is_active', true)
                    ->where('is_delivery_active', true)
                    ->when($countryId, fn ($rule) => $rule->where('country_id', $countryId)),
            ],
            'city_id' => ['prohibited'],
            'district_id' => ['prohibited'],
            'formatted_address' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
            'street' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
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
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'sometimes',
                'nullable',
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
            'country_id.exists' => 'الدولة المحددة غير متاحة للتوصيل حالياً.',
            'governorate_id.exists' => 'المحافظة المحددة غير متاحة للتوصيل أو لا تنتمي إلى الدولة المختارة.',
            'customer_id.prohibited' => 'لا يمكن تعديل ملكية العنوان لعميل آخر.',
            'latitude.required' => 'يرجى تحديد موقع العنوان على خريطة Google.',
            'longitude.required' => 'يرجى تحديد موقع العنوان على خريطة Google.',
            'formatted_address.required' => 'يرجى كتابة العنوان بوضوح.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $changesLocation = $this->hasAny([
                'formatted_address',
                'street',
                'latitude',
                'longitude',
                'label',
            ]);
            if (! $changesLocation) {
                return;
            }

            // When saving address/location fields, coordinates are mandatory
            if ($this->exists('latitude') || $this->exists('longitude')
                || $this->exists('formatted_address') || $this->exists('street')
                || $this->exists('label')) {
                $lat = $this->input('latitude');
                $lng = $this->input('longitude');
                // Only enforce when the client is sending a location update payload
                if ($this->hasAny(['latitude', 'longitude', 'formatted_address', 'street'])) {
                    if ($lat === null || $lat === '') {
                        $validator->errors()->add('latitude', 'يرجى تحديد موقع العنوان على خريطة Google.');
                    }
                    if ($lng === null || $lng === '') {
                        $validator->errors()->add('longitude', 'يرجى تحديد موقع العنوان على خريطة Google.');
                    }
                }
            }

            if ($this->exists('formatted_address')
                && trim((string) $this->input('formatted_address', '')) === '') {
                $validator->errors()->add('formatted_address', 'يرجى كتابة العنوان بوضوح.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات تحديث العنوان غير صالحة.'
            )
        );
    }
}
