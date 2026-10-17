<?php

namespace App\Http\Requests;

use App\DTOs\CreateTreasuryCurrencyAccountData;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTreasuryCurrencyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ((string) $this->input('accountable_type')) {
            'cashbox', Cashbox::class => 'admin.cashboxes.update',
            'bank_account', BankAccount::class => 'admin.bank_accounts.update',
            'electronic_wallet', ElectronicWalletAccount::class => 'admin.electronic_wallets.update',
            default => null,
        };

        return $permission !== null && (bool) $this->user()?->hasPermission($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accountable_type' => [
                'required',
                'string',
                'in:'.Cashbox::class.','.BankAccount::class.','.ElectronicWalletAccount::class.',cashbox,bank_account,electronic_wallet',
            ],
            'accountable_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = (string) $this->input('accountable_type');
            $resolvedType = match ($type) {
                'cashbox', Cashbox::class => Cashbox::class,
                'bank_account', BankAccount::class => BankAccount::class,
                'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
                default => $type,
            };

            $exists = TreasuryCurrencyAccount::where('accountable_type', $resolvedType)
                ->where('accountable_id', $this->input('accountable_id'))
                ->where('currency_id', $this->input('currency_id'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('currency_id', 'العملة مضافة بالفعل لحساب الخزينة المحدد.');
            }
        });
    }

    public function toDto(): CreateTreasuryCurrencyAccountData
    {
        $type = (string) $this->input('accountable_type');
        $resolvedType = match ($type) {
            'cashbox', Cashbox::class => Cashbox::class,
            'bank_account', BankAccount::class => BankAccount::class,
            'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
            default => $type,
        };

        return new CreateTreasuryCurrencyAccountData(
            accountableType: $resolvedType,
            accountableId: (int) $this->input('accountable_id'),
            currencyId: (int) $this->input('currency_id'),
            glAccountId: 0,
            notes: $this->input('notes'),
        );
    }
}
