<?php

namespace App\Http\Requests;

use App\DTOs\CreateJournalEntryData;
use App\Support\DateFilterNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.journal_entries.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('entry_date')) {
            $this->merge(['entry_date' => DateFilterNormalizer::normalize($this->input('entry_date'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accounting_period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate_snapshot' => ['nullable', 'numeric', 'gt:0'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.transaction_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'lines.*.transaction_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.party_type' => ['nullable', 'string', 'max:255'],
            'lines.*.party_id' => ['nullable', 'integer'],
        ];
    }

    public function toDto(): CreateJournalEntryData
    {
        return CreateJournalEntryData::fromArray($this->validated());
    }
}
