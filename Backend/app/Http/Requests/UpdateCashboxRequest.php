<?php

namespace App\Http\Requests;

use App\DTOs\CashboxData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCashboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.cashboxes.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cashboxId = $this->route('cashbox')->id;

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('cashboxes', 'code')->ignore($cashboxId)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'currency_ids' => ['required', 'array', 'min:1'],
            'currency_ids.*' => ['integer', 'distinct', 'exists:currencies,id'],
            'gl_account_id' => ['required', 'integer', 'exists:accounts,id'],
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
