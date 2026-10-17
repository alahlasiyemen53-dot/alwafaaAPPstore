<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\ReportDateRangeData;
use App\DTOs\ReportFilterData;
use App\Support\DateFilterNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared, validated filter contract for every Sprint 18 report and dashboard
 * endpoint. Reports interpret `sort` against their own whitelist of columns;
 * this request only guarantees the value is a safe identifier-shaped string.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('from')) {
            $this->merge(['from' => DateFilterNormalizer::normalize($this->input('from'))]);
        }
        if ($this->has('to')) {
            $this->merge(['to' => DateFilterNormalizer::normalize($this->input('to'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'custom'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'sort' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toData(string $defaultSort = 'id'): ReportFilterData
    {
        $validated = $this->validated();

        $preset = (string) ($validated['preset'] ?? 'this_month');
        $from = DateFilterNormalizer::normalize($validated['from'] ?? null);
        $to = DateFilterNormalizer::normalize($validated['to'] ?? null);
        $range = ReportDateRangeData::fromPreset($preset, $from, $to);

        return new ReportFilterData(
            range: $range,
            currencyId: isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
            warehouseId: isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            customerId: isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            supplierId: isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            driverId: isset($validated['driver_id']) ? (int) $validated['driver_id'] : null,
            sort: (string) ($validated['sort'] ?? $defaultSort),
            direction: (string) ($validated['direction'] ?? 'desc'),
            perPage: isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
        );
    }
}
