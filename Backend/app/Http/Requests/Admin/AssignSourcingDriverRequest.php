<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Driver;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class AssignSourcingDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $driver = Driver::find($value);
                    if ($driver && ! $driver->is_active) {
                        $fail('المندوب المحدد معطل حالياً ولا يمكن إسناد مهمة التوريد إليه.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'driver_id' => 'مندوب التوصيل',
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.required' => 'يجب اختيار مندوب التوصيل.',
            'driver_id.exists' => 'المندوب المحدد غير موجود في النظام.',
        ];
    }
}
