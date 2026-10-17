<?php

namespace App\Http\Requests;

use App\DTOs\CashboxData;
use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCashboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.cashboxes.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:cashboxes,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'currency_ids' => ['required', 'array', 'min:1'],
            'currency_ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
            'gl_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('type', AccountType::Asset)->where('is_postable', true)),
            ],
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

    public function toDto(): CashboxData
    {
        /** @var list<int> $currencyIds */
        $currencyIds = array_map('intval', $this->validated('currency_ids'));

        return new CashboxData(
            code: (string) $this->validated('code'),
            nameAr: (string) $this->validated('name_ar'),
            nameEn: $this->validated('name_en'),
            currencyId: $currencyIds[0],
            glAccountId: (int) $this->validated('gl_account_id'),
            isActive: (bool) $this->boolean('is_active', true),
            notes: $this->validated('notes'),
            currencyIds: $currencyIds,
        );
    }
}
