<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\MarkDeliveryFailedData;
use App\Enums\DeliveryFailureReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class MarkDeliveryFailedRequest extends FormRequest
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
            'failure_reason' => ['required', new Enum(DeliveryFailureReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'failure_reason.required' => 'يرجى تحديد سبب تعذر التوصيل.',
            'failure_reason.Illuminate\Validation\Rules\Enum' => 'سبب تعذر التوصيل المختار غير صالح.',
        ];
    }

    public function toDto(): MarkDeliveryFailedData
    {
        return MarkDeliveryFailedData::fromArray($this->validated());
    }
}
