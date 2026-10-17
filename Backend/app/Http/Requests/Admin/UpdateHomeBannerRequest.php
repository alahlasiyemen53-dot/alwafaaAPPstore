<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerActionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateHomeBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'action_type' => ['required', Rule::enum(BannerActionType::class)],
            'action_reference' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,jpg', 'max:4096'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $actionType = $this->input('action_type');
                $reference = $this->input('action_reference');

                if ($actionType && $actionType !== BannerActionType::None->value) {
                    if (empty($reference)) {
                        $validator->errors()->add('action_reference', 'يرجى تحديد الكيان المرتبط بوجهة البنر.');
                        return;
                    }

                    $exists = match ($actionType) {
                        BannerActionType::Product->value => Product::query()->where('id', $reference)->exists(),
                        BannerActionType::Category->value => Category::query()->where('id', $reference)->exists(),
                        BannerActionType::Promotion->value => Promotion::query()->where('id', $reference)->exists(),
                        default => false,
                    };

                    if (! $exists) {
                        $validator->errors()->add('action_reference', 'الكيان المختار كوجهة للبنر غير موجود أو غير صالح.');
                    }
                }
            }
        ];
    }

    public function attributes(): array
    {
        return [
            'title_ar' => 'العنوان العربي',
            'title_en' => 'العنوان الإنجليزي',
            'subtitle_ar' => 'الوصف الترويجي بالعربية',
            'subtitle_en' => 'الوصف الترويجي بالإنجليزية',
            'is_active' => 'حالة التفعيل',
            'sort_order' => 'ترتيب الظهور',
            'starts_at' => 'تاريخ بدء العرض',
            'ends_at' => 'تاريخ انتهاء العرض',
            'action_type' => 'نوع وجهة البنر',
            'action_reference' => 'الوجهة المرتبطة',
            'image' => 'صورة البنر',
        ];
    }
}
