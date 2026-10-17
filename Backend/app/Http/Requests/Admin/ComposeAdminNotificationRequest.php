<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ComposeAdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->hasPermission('admin.notifications.send_test')
            || $user->hasPermission('admin.notifications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::in(['all', 'customer', 'segment'])],
            'customer_id' => [
                'nullable',
                'required_if:audience,customer',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'segment' => [
                'nullable',
                'required_if:audience,segment',
                'string',
                Rule::in(['active_30_days', 'inactive_30_days', 'abandoned_cart', 'with_orders']),
            ],
            'platform' => ['nullable', 'string', Rule::in(['all', 'ios', 'android'])],
            'governorate_id' => [
                'nullable',
                'integer',
                Rule::exists('governorates', 'id')->where('is_active', true),
            ],
            'governorate_target' => ['nullable', 'string', Rule::in(['registered', 'address', 'any'])],
            'send_type' => ['nullable', 'string', Rule::in(['now', 'scheduled'])],
            'scheduled_at' => [
                'nullable',
                'required_if:send_type,scheduled',
                'date',
                'after:now',
            ],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'destination_type' => ['nullable', 'string', Rule::in(['none', 'product', 'collection', 'category', 'order'])],
            'destination_id' => [
                'nullable',
                'required_if:destination_type,product,collection,category,order',
                'string',
                'max:64',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audience.required' => 'يرجى اختيار جمهور الإشعار.',
            'customer_id.required_if' => 'يرجى اختيار العميل المستهدف.',
            'segment.required_if' => 'يرجى اختيار شريحة العملاء المستهدفة.',
            'segment.in' => 'الشريحة المحددة غير صالحة.',
            'scheduled_at.required_if' => 'يرجى تحديد وقت وتاريخ الإرسال المجدول.',
            'scheduled_at.after' => 'يجب أن يكون وقت الإرسال المجدول في وقت مستقبلي.',
            'title.required' => 'عنوان الإشعار مطلوب.',
            'body.required' => 'محتوى الإشعار مطلوب.',
            'image.image' => 'يجب أن يكون الملف صورة صالحة.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'destination_type.in' => 'نوع الوجهة المحددة غير صالح.',
            'destination_id.required_if' => 'يرجى تحديد المعرّف الخاص بالوجهة المختارة.',
        ];
    }
}
