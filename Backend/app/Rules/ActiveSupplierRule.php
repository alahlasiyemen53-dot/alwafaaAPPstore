<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Supplier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ActiveSupplierRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $supplier = Supplier::query()->find($value);
        if ($supplier === null) {
            return;
        }

        if (! $supplier->is_active) {
            $fail('لا يمكن تعيين مورد معطل. اختر مورداً نشطاً أو اترك الحقل فارغاً.');
        }
    }
}
