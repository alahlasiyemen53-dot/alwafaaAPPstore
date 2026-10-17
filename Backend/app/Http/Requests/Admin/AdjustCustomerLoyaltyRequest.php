<?php

namespace App\Http\Requests\Admin;

use App\DTOs\LoyaltyAdjustmentData;
use Illuminate\Foundation\Http\FormRequest;

final class AdjustCustomerLoyaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.loyalty.adjust') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'type' => ['required', 'string', 'in:credit,debit'],
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function toDto(): LoyaltyAdjustmentData
    {
        return new LoyaltyAdjustmentData(
            customerId: (int) $this->validated('customer_id'),
            points: (int) $this->validated('points'),
            type: (string) $this->validated('type'),
            reason: (string) $this->validated('reason'),
            createdBy: (int) $this->user()?->id,
        );
    }
}
