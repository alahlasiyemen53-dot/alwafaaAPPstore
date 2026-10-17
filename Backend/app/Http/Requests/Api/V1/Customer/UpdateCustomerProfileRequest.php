<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->user('customer_jwt');
        $customerId = $customer?->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:100',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],
            'phone' => [
                'prohibited',
            ],
            'is_active' => [
                'prohibited',
            ],
            'tier' => [
                'prohibited',
            ],
            'current_tier_id' => [
                'prohibited',
            ],
            'wallet' => [
                'prohibited',
            ],
            'loyalty' => [
                'prohibited',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.string' => 'الاسم يجب أن يكون نصاً صالحاً.',
            'name.min' => 'الاسم يجب ألا يقل عن حرفين.',
            'name.max' => 'الاسم لا يجب أن يتجاوز 100 حرف.',
            'email.email' => 'البريد الإلكتروني المدخل غير صالح.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل من قبل حساب آخر.',
            'phone.prohibited' => 'لا يمكن تعديل رقم الهاتف مباشرة من الملف الشخصي؛ يتطلب التحقق برمز OTP.',
            'is_active.prohibited' => 'لا يمكن تعديل حالة الحساب من الملف الشخصي.',
            'tier.prohibited' => 'لا يمكن تعديل فئة العميل من الملف الشخصي.',
            'current_tier_id.prohibited' => 'لا يمكن تعديل فئة العميل من الملف الشخصي.',
            'wallet.prohibited' => 'لا يمكن تعديل المحفظة من الملف الشخصي.',
            'loyalty.prohibited' => 'لا يمكن تعديل نقاط الولاء من الملف الشخصي.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'بيانات تحديث الملف الشخصي غير صالحة.'
            )
        );
    }
}
