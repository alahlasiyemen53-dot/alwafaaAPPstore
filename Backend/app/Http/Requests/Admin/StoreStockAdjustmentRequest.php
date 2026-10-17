<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'reason_code' => ['required', new Enum(StockAdjustmentReason::class)],
            'adjustment_number' => ['nullable', 'string', 'max:64', 'unique:stock_adjustments,adjustment_number'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.direction' => ['required', 'string', 'in:in,out'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
