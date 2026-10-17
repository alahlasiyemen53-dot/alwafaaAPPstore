<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\DriverCodSettlementData;
use App\DTOs\DriverCodSettlementItemData;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDriverCodSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.driver_cod_settlements.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'treasury_account_type' => ['required', 'string', 'in:'.Cashbox::class.','.BankAccount::class.','.ElectronicWalletAccount::class.',cashbox,bank_account,electronic_wallet'],
            'treasury_account_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'items.*.settlement_amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action_type' => ['nullable', 'string', 'in:draft,post'],
        ];
    }

    public function toDTO(): DriverCodSettlementData
    {
        $type = (string) $this->input('treasury_account_type');
        $resolvedType = match ($type) {
            'cashbox', Cashbox::class => Cashbox::class,
            'bank_account', BankAccount::class => BankAccount::class,
            'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::class,
            default => $type,
        };

        $items = [];
        foreach ((array) $this->input('items', []) as $item) {
            $items[] = new DriverCodSettlementItemData(
                deliveryId: (int) $item['delivery_id'],
                settlementAmount: (float) $item['settlement_amount'],
            );
        }

        return new DriverCodSettlementData(
            driverId: (int) $this->input('driver_id'),
            currencyId: (int) $this->input('currency_id'),
            treasuryAccountType: $resolvedType,
            treasuryAccountId: (int) $this->input('treasury_account_id'),
            items: $items,
            notes: $this->input('notes') ? (string) $this->input('notes') : null,
            postDirectly: $this->input('action_type') === 'post',
        );
    }
}
