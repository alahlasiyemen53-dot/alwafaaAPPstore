<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('admin.customers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! PhoneNormalizer::isValid((string) $value)) {
                        $fail('رقم الهاتف المدخل غير صالح.');

                        return;
                    }

                    $normalized = PhoneNormalizer::normalize((string) $value);
                    if (Customer::query()->where('normalized_phone', $normalized)->exists()) {
                        $fail('رقم الهاتف مستخدم بالفعل لعميل آخر.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم العميل مطلوب.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
        ];
    }
}
