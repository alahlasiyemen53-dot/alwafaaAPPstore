<?php

namespace App\Http\Requests\Admin;

use App\Models\Governorate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('admin.customer_addresses.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'governorate_id' => [
                'required',
                'integer',
                'exists:governorates,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $countryId = $this->input('country_id');
                    if ($value && $countryId) {
                        $belongs = Governorate::query()
                            ->where('id', $value)
                            ->where('country_id', $countryId)
                            ->exists();

                        if (! $belongs) {
                            $fail('المحافظة المختارة لا تتبع الدولة المحددة.');
                        }
                    }
                },
            ],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:128'],
            'floor' => ['nullable', 'string', 'max:64'],
            'apartment' => ['nullable', 'string', 'max:64'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'اسم العنوان مطلوب (مثلاً: المنزل، العمل).',
            'country_id.required' => 'الدولة مطلوبة.',
            'governorate_id.required' => 'المحافظة مطلوبة.',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90 درجة.',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180 درجة.',
        ];
    }
}
