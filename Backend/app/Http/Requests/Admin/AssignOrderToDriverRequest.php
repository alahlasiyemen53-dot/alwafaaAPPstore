<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\AssignOrderToDriverData;
use Illuminate\Foundation\Http\FormRequest;

final class AssignOrderToDriverRequest extends FormRequest
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
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'driver_id.required' => 'يرجى اختيار مندوب التوصيل.',
            'driver_id.exists' => 'مندوب التوصيل المختار غير موجود.',
        ];
    }

    public function toDto(): AssignOrderToDriverData
    {
        return AssignOrderToDriverData::fromArray($this->validated());
    }
}
