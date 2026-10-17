<?php

namespace App\Http\Requests\Admin;

use App\DTOs\CustomerWalletRefundData;
use Illuminate\Foundation\Http\FormRequest;

final class RefundToCustomerWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.customer_wallets.refund') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sales_return_id' => ['required', 'integer', 'exists:sales_returns,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toDto(): CustomerWalletRefundData
    {
        return new CustomerWalletRefundData(
            salesReturnId: (int) $this->validated('sales_return_id'),
            reason: $this->validated('reason'),
            refundedBy: (int) $this->user()?->id,
        );
    }
}
