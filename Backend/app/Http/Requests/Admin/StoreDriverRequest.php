<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\CreateDriverData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDriverRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', Rule::unique('drivers', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('drivers', 'email')],
            'license_number' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المندوب مطلوب.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'رقم الهاتف مسجل مسبقاً لمندوب آخر.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً لمندوب آخر.',
        ];
    }

    public function toDto(): CreateDriverData
    {
        return CreateDriverData::fromArray($this->validated());
    }
}
