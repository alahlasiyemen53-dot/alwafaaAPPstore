<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PaymentChannel;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class CustomerPaymentMethodWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $methodId = $this->route('method')?->id ?? $this->route('method');

        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('customer_payment_methods', 'code')->ignore($methodId),
            ],
            'provider_code' => ['required', new Enum(PaymentProviderCode::class)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'instructions_ar' => ['nullable', 'string', 'max:1000'],
            'instructions_en' => ['nullable', 'string', 'max:1000'],
            'channel' => ['required', new Enum(PaymentChannel::class)],
            'flow_type' => ['required', new Enum(PaymentFlowType::class)],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'electronic_wallet_account_id' => [
                Rule::requiredIf(fn () => $this->input('provider_code') !== PaymentProviderCode::Cod->value),
                'nullable',
                'integer',
                'exists:electronic_wallet_accounts,id',
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
