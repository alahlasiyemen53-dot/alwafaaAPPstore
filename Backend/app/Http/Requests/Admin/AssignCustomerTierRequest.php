<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AssignCustomerTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.customer_tiers.assign') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tier_id' => ['required', 'integer', 'exists:customer_tiers,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tier_id.required' => 'يرجى اختيار المستوى المراد التعيين إليه.',
            'tier_id.exists' => 'المستوى المحدد غير موجود.',
            'reason.required' => 'سبب تغيير المستوى إلزامي.',
            'reason.min' => 'يجب ألا يقل سبب التغيير عن 5 أحرف لتوضيح المبرر الإداري.',
            'reason.max' => 'يجب ألا يتجاوز سبب التغيير 500 حرف.',
        ];
    }
}
