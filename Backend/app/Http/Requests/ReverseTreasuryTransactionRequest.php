<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseTreasuryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.treasury_transactions.reverse') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
