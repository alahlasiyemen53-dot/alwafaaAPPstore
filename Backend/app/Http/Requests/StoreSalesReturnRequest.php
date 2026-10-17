<?php

namespace App\Http\Requests;

use App\DTOs\SalesReturnData;
use App\DTOs\SalesReturnItemData;
use App\Enums\ReturnReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSalesReturnRequest extends FormRequest
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
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'reason_code' => ['required', 'string', Rule::enum(ReturnReasonCode::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', 'string', 'in:draft,post'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.return_quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.reason_code' => ['nullable', 'string', Rule::enum(ReturnReasonCode::class)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toDto(): SalesReturnData
    {
        $items = array_map(function ($item): SalesReturnItemData {
            return new SalesReturnItemData(
                orderItemId: (int) $item['order_item_id'],
                productVariantId: (int) $item['product_variant_id'],
                returnQuantity: (float) $item['return_quantity'],
                reasonCode: $item['reason_code'] ?? null,
                notes: $item['notes'] ?? null,
            );
        }, $this->validated('items'));

        return new SalesReturnData(
            orderId: (int) $this->validated('order_id'),
            warehouseId: (int) $this->validated('warehouse_id'),
            reasonCode: $this->validated('reason_code'),
            notes: $this->validated('notes'),
            items: $items,
        );
    }
}
