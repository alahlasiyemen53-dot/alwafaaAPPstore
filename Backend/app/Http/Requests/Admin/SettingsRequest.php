<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array|min:1',
            'settings.*' => 'nullable|string|max:1000',
            'settings.default_currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'settings.support_email' => 'nullable|email|max:255',
            'settings.gift_wrapping_enabled' => 'nullable|boolean',
            'settings.gift_message_enabled' => 'nullable|boolean',
            'settings.gift_message_max_length' => 'nullable|integer|min:10|max:1000',
            'settings.support_phone_enabled' => 'nullable|boolean',
            'settings.support_phone' => 'nullable|string|max:32',
            'settings.support_whatsapp_enabled' => 'nullable|boolean',
            'settings.support_whatsapp_number' => 'nullable|string|max:32',
            'settings.support_whatsapp_default_message' => 'nullable|string|max:500',
            'settings.support_hours_text_ar' => 'nullable|string|max:255',
            'settings.customer_delivery_confirm_hours' => 'nullable|integer|min:1|max:168',
            'settings.app_latest_version' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+\.\d+\.\d+(\+\d+)?$/',
            ],
            'settings.app_min_version' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\d+\.\d+\.\d+(\+\d+)?$/',
            ],
            'settings.app_force_update' => 'nullable|boolean',
            'settings.app_android_store_url' => 'nullable|url|max:500',
            'settings.app_ios_store_url' => 'nullable|url|max:500',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $settings = $this->input('settings', []);

            $phoneEnabled = filter_var($settings['support_phone_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $phone = trim((string) ($settings['support_phone'] ?? ''));

            if ($phoneEnabled) {
                if ($phone === '') {
                    $validator->errors()->add('settings.support_phone', 'يرجى إدخال رقم هاتف الدعم عند تفعيل الاتصال الهاتفي.');
                } elseif (! \App\Support\PhoneNormalizer::isValid($phone)) {
                    $validator->errors()->add('settings.support_phone', 'رقم هاتف الدعم غير صالح. يرجى إدخال رقم هاتف محلي أو دولي صحيح.');
                }
            }

            $whatsappEnabled = filter_var($settings['support_whatsapp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $whatsappNumber = trim((string) ($settings['support_whatsapp_number'] ?? ''));

            if ($whatsappEnabled) {
                if ($whatsappNumber === '') {
                    $validator->errors()->add('settings.support_whatsapp_number', 'يرجى إدخال رقم واتساب الدعم عند تفعيل محادثات واتساب.');
                } elseif (! \App\Support\PhoneNormalizer::isValid($whatsappNumber)) {
                    $validator->errors()->add('settings.support_whatsapp_number', 'رقم واتساب الدعم غير صالح. يرجى إدخال رقم هاتف محلي أو دولي صحيح.');
                }
            }
        });
    }
}
