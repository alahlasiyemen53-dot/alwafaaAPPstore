<?php

namespace App\Http\Requests;

use App\DTOs\TreasuryTransferData;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.treasury_transfers.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $typeMap = [
            'cashbox' => Cashbox::class,
            'bank_account' => BankAccount::class,
            'electronic_wallet' => ElectronicWalletAccount::class,
            Cashbox::class => Cashbox::class,
            BankAccount::class => BankAccount::class,
            ElectronicWalletAccount::class => ElectronicWalletAccount::class,
        ];

        $sourceType = $this->input('source_type', $this->input('from_account_type'));
        $destinationType = $this->input('destination_type', $this->input('to_account_type'));

        $this->merge([
            'source_type' => $typeMap[(string) $sourceType] ?? $sourceType,
            'source_id' => $this->input('source_id', $this->input('from_account_id')),
            'destination_type' => $typeMap[(string) $destinationType] ?? $destinationType,
            'destination_id' => $this->input('destination_id', $this->input('to_account_id')),
            'exchange_rate_snapshot' => $this->input('exchange_rate_snapshot', $this->input('exchange_rate')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', Rule::in([
                Cashbox::class,
                BankAccount::class,
                ElectronicWalletAccount::class,
            ])],
            'source_id' => ['required', 'integer'],
            'destination_type' => ['required', 'string', Rule::in([
                Cashbox::class,
                BankAccount::class,
                ElectronicWalletAccount::class,
            ])],
            'destination_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'exchange_rate_snapshot' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            if ($this->input('source_type') === $this->input('destination_type') &&
                $this->input('source_id') == $this->input('destination_id')) {
                $v->errors()->add('destination_id', 'لا يمكن التحويل إلى نفس الحساب.');
            }
        });
    }

    public function toDto(): TreasuryTransferData
    {
        return new TreasuryTransferData(
            sourceType: (string) $this->validated('source_type'),
            sourceId: (int) $this->validated('source_id'),
            destinationType: (string) $this->validated('destination_type'),
            destinationId: (int) $this->validated('destination_id'),
            amount: $this->validated('amount'),
            description: (string) $this->validated('description'),
            referenceNumber: $this->validated('reference_number'),
            exchangeRateSnapshot: $this->validated('exchange_rate_snapshot'),
        );
    }
}
