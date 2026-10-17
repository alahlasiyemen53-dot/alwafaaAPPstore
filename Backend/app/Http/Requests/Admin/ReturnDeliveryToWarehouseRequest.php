<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\ReturnDeliveryToWarehouseData;
use Illuminate\Foundation\Http\FormRequest;

final class ReturnDeliveryToWarehouseRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): ReturnDeliveryToWarehouseData
    {
        return ReturnDeliveryToWarehouseData::fromArray($this->validated());
    }
}
