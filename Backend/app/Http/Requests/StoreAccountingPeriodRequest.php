<?php

namespace App\Http\Requests;

use App\DTOs\CreateAccountingPeriodData;
use App\Support\DateFilterNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.accounting_periods.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('starts_at')) {
            $this->merge(['starts_at' => DateFilterNormalizer::normalize($this->input('starts_at'))]);
        }
        if ($this->has('ends_at')) {
            $this->merge(['ends_at' => DateFilterNormalizer::normalize($this->input('ends_at'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function toDto(): CreateAccountingPeriodData
    {
        return CreateAccountingPeriodData::fromArray($this->validated());
    }
}
