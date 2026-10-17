<?php

namespace App\Http\Requests;

use App\DTOs\ElectronicWalletAccountData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreElectronicWalletAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.electronic_wallets.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:electronic_wallet_accounts,code'],
            'provider_name' => ['required', 'string', 'max:255'],
            'account_label' => ['required', 'string', 'max:255'],
            'account_identifier' => ['required', 'string', 'max:128'],
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

    public function toDto(): ElectronicWalletAccountData
    {
        /** @var list<int> $currencyIds */
        $currencyIds = array_map('intval', $this->validated('currency_ids'));

        return new ElectronicWalletAccountData(
            code: (string) $this->validated('code'),
            providerName: (string) $this->validated('provider_name'),
            accountLabel: (string) $this->validated('account_label'),
            accountIdentifier: (string) $this->validated('account_identifier'),
            currencyId: $currencyIds[0],
            glAccountId: (int) $this->validated('gl_account_id'),
            isActive: (bool) $this->boolean('is_active', true),
            notes: $this->validated('notes'),
            currencyIds: $currencyIds,
        );
    }
}
