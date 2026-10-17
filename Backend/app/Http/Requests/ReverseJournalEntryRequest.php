<?php

namespace App\Http\Requests;

use App\DTOs\ReverseJournalEntryData;
use App\Support\DateFilterNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.journal_entries.reverse') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reversal_date')) {
            $this->merge(['reversal_date' => DateFilterNormalizer::normalize($this->input('reversal_date'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'reversal_date' => ['required', 'date'],
            'accounting_period_id' => ['nullable', 'integer', 'exists:accounting_periods,id'],
        ];
    }

    public function toDto(): ReverseJournalEntryData
    {
        return ReverseJournalEntryData::fromArray($this->validated());
    }
}
