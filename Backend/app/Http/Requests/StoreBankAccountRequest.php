<?php

namespace App\Http\Requests;

use App\DTOs\BankAccountData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.bank_accounts.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:bank_accounts,code'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:128'],
            'iban' => ['nullable', 'string', 'max:128'],
            'currency_ids' => ['required', 'array', 'min:1'],
            'currency_ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
            'gl_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_ids.required' => 'يجب اختيار عملة واحدة على الأقل.',
            'currency_ids.min' => 'يجب اختيار عملة واحدة على الأقل.',
        ];
    }

    public function toDto(): BankAccountData
    {
        /** @var list<int> $currencyIds */
        $currencyIds = array_map('intval', $this->validated('currency_ids'));

        return new BankAccountData(
            code: (string) $this->validated('code'),
            bankName: (string) $this->validated('bank_name'),
            accountName: (string) $this->validated('account_name'),
            accountNumber: (string) $this->validated('account_number'),
            iban: $this->validated('iban'),
            currencyId: $currencyIds[0],
            glAccountId: (int) $this->validated('gl_account_id'),
            isActive: (bool) $this->boolean('is_active', true),
            notes: $this->validated('notes'),
            currencyIds: $currencyIds,
        );
    }
}
