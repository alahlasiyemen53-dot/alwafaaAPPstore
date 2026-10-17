<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('attribute_value_ids')) {
            $merge['attribute_value_ids'] = array_values(array_filter(
                (array) $this->input('attribute_value_ids'),
                fn ($val) => $val !== null && $val !== '' && is_numeric($val) && (int) $val > 0
            ));
        }

        if ($this->has('barcode')) {
            $barcode = trim((string) $this->input('barcode'));
            $merge['barcode'] = $barcode === '' ? null : $barcode;
        }

        if ($this->has('compare_at_price')) {
            $compare = trim((string) $this->input('compare_at_price'));
            $merge['compare_at_price'] = $compare === '' ? null : $compare;
        }

        if ($this->has('supplier_item_number')) {
            $itemNo = trim((string) $this->input('supplier_item_number'));
            $merge['supplier_item_number'] = $itemNo === '' ? null : $itemNo;
        }

        if (! $this->filled('fulfillment_mode')) {
            $variant = $this->route('variant');
            $currentMode = ($variant instanceof ProductVariant ? $variant->fulfillment_mode : null)
                ?? ProductVariantFulfillmentMode::Stocked;
            $merge['fulfillment_mode'] = is_object($currentMode) ? $currentMode->value : (string) $currentMode;
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->id : $variant;

        return [
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('product_variants', 'barcode')->ignore($variantId)],
            'status' => ['required', new Enum(ProductVariantStatus::class)],
            'fulfillment_mode' => ['required', new Enum(ProductVariantFulfillmentMode::class)],
            'supplier_id' => [
                'required_if:fulfillment_mode,on_demand',
                'nullable',
                'integer',
                'exists:suppliers,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('fulfillment_mode') === ProductVariantFulfillmentMode::OnDemand->value && $value) {
                        $supplier = Supplier::find($value);
                        if ($supplier && ! $supplier->is_active) {
                            $fail('المورد المحدد معطل ولا يمكن استخدامه للتوفير عند الطلب.');
                        }
                    }
                },
            ],
            'is_default' => ['nullable', 'boolean'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'gt:price'],
            'supplier_item_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'رمز التخزين (SKU)',
            'barcode' => 'الباركود',
            'price' => 'السعر بالعملة الأساسية',
            'compare_at_price' => 'سعر المقارنة',
            'status' => 'الحالة',
            'fulfillment_mode' => 'طريقة التوفير',
            'supplier_id' => 'المورد',
            'supplier_item_number' => 'رقم الصنف لدى المورد',
            'attribute_value_ids' => 'خصائص المتغير',
            'attribute_value_ids.*' => 'خاصية المتغير',
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'رمز التخزين (SKU) مطلوب.',
            'sku.unique' => 'رمز التخزين (SKU) مستخدم بالفعل.',
            'barcode.unique' => 'الباركود مستخدم بالفعل.',
            'price.required' => 'السعر بالعملة الأساسية مطلوب.',
            'price.numeric' => 'يجب أن يكون السعر رقماً صحيحاً أو عشرياً.',
            'price.min' => 'يجب ألا يقل السعر عن 0.',
            'compare_at_price.numeric' => 'يجب أن يكون سعر المقارنة رقماً.',
            'compare_at_price.min' => 'يجب ألا يقل سعر المقارنة عن 0.',
            'compare_at_price.gt' => 'سعر المقارنة يجب أن يكون أعلى من السعر الأساسي.',
            'attribute_value_ids.*.integer' => 'معرف الخاصية غير صالح (يجب أن يكون رقماً صحيحاً).',
            'attribute_value_ids.*.exists' => 'إحدى قيم الخصائص المحددة غير موجودة في النظام.',
            'status.required' => 'حالة المتغير مطلوبة.',
            'fulfillment_mode.required' => 'طريقة التوفير مطلوبة.',
            'supplier_id.required_if' => 'يجب اختيار المورد عند تحديد "توفير عند الطلب".',
            'supplier_id.exists' => 'المورد المحدد غير موجود في النظام.',
        ];
    }
}
