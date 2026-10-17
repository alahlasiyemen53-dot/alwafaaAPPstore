<?php

namespace App\Http\Requests;

use App\DTOs\CreateAccountData;
use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.accounts.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z\.\-_]+$/', Rule::unique('accounts', 'code')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'normal_balance' => ['nullable', Rule::enum(AccountNormalBalance::class)],
            'is_postable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): CreateAccountData
    {
        return CreateAccountData::fromArray($this->validated());
    }
}
