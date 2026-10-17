<?php

namespace App\Http\Requests;

use App\DTOs\TreasuryTransactionData;
use App\Enums\TreasuryTransactionType;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTreasuryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.treasury_transactions.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_type' => ['required', 'string', Rule::enum(TreasuryTransactionType::class)],
            'account_type' => ['required', 'string', Rule::in([
                Cashbox::class,
                BankAccount::class,
                ElectronicWalletAccount::class,
            ])],
            'account_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:1000'],
            'offset_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'counterparty_type' => ['nullable', 'string', 'max:64'],
            'counterparty_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'exchange_rate_snapshot' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function toDto(): TreasuryTransactionData
    {
        return new TreasuryTransactionData(
            transactionType: TreasuryTransactionType::from($this->validated('transaction_type')),
            accountType: (string) $this->validated('account_type'),
            accountId: (int) $this->validated('account_id'),
            currencyId: (int) $this->validated('currency_id'),
            amount: $this->validated('amount'),
            description: (string) $this->validated('description'),
            offsetAccountId: $this->validated('offset_account_id') ? (int) $this->validated('offset_account_id') : null,
            counterpartyType: $this->validated('counterparty_type'),
            counterpartyId: $this->validated('counterparty_id') ? (int) $this->validated('counterparty_id') : null,
            referenceNumber: $this->validated('reference_number'),
            exchangeRateSnapshot: $this->validated('exchange_rate_snapshot'),
        );
    }
}
