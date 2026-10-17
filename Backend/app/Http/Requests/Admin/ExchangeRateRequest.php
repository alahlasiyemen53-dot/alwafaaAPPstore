<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('effective_at') && is_string($this->effective_at)) {
            $value = trim($this->effective_at);

            // Convert Eastern Arabic numerals (٠-٩) to Western Arabic (0-9)
            $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $value = str_replace($eastern, $western, $value);

            // Fix duplicate time concatenation (e.g. "YYYY-MM-DD HH:MM HH:MM")
            if (preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})\s+\d{2}:\d{2}$/', $value, $matches)) {
                $value = $matches[1];
            }

            // If date is provided as DD/MM/YYYY or DD-MM-YYYY
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})(.*)$/', $value, $matches)) {
                $value = "{$matches[3]}-{$matches[2]}-{$matches[1]}".trim($matches[4]);
            }

            try {
                $parsed = Carbon::parse($value);
                $this->merge(['effective_at' => $parsed->format('Y-m-d H:i:s')]);
            } catch (\Throwable) {
                $this->merge(['effective_at' => $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'target_currency_id' => 'nullable|exists:currencies,id',
            'from_currency_id' => 'nullable|exists:currencies,id',
            'to_currency_id' => 'nullable|exists:currencies,id',
            'rate' => 'required|numeric|gt:0',
            'effective_at' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'target_currency_id.required' => 'يرجى اختيار العملة المقابلة.',
            'target_currency_id.exists' => 'العملة المقابلة المحددة غير موجودة.',
            'from_currency_id.required' => 'يرجى اختيار العملة المقابلة.',
            'from_currency_id.exists' => 'العملة المقابلة المحددة غير موجودة.',
            'rate.required' => 'سعر الصرف مطلوب.',
            'rate.numeric' => 'سعر الصرف يجب أن يكون قيمة رقمية.',
            'rate.gt' => 'سعر الصرف يجب أن يكون أكبر من الصفر.',
            'effective_at.required' => 'تاريخ ووقت السريان مطلوب.',
            'effective_at.date' => 'صيغة تاريخ ووقت السريان غير صالحة. يرجى إدخال تاريخ صالح (مثل: 2026-09-16 12:00).',
        ];
    }
}
