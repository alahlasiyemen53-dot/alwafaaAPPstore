<?php

namespace App\Http\Requests\Admin;

use App\DTOs\CustomerWalletAdjustmentData;
use Illuminate\Foundation\Http\FormRequest;

final class AdjustCustomerWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.customer_wallets.adjust') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'type' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function toDto(): CustomerWalletAdjustmentData
    {
        return new CustomerWalletAdjustmentData(
            customerId: (int) $this->validated('customer_id'),
            currencyId: (int) $this->validated('currency_id'),
            amount: (string) $this->validated('amount'),
            type: (string) $this->validated('type'),
            reason: (string) $this->validated('reason'),
            createdBy: (int) $this->user()?->id,
        );
    }
}
