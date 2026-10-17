<?php

namespace App\Actions;

use App\Data\AttributeValueData;
use App\Models\AttributeValue;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateAttributeValueAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(AttributeValue $value, AttributeValueData $data): AttributeValue
    {
        return DB::transaction(function () use ($value, $data): AttributeValue {
            $before = $value->only(['value_ar', 'value_en', 'code', 'sort_order', 'is_active']);
            $updated = $this->attributes->updateValue($value, $data);
            $this->auditLogger->record('admin.attribute_value.updated', $updated, metadata: [
                'before' => $before,
                'after' => $updated->only(['value_ar', 'value_en', 'code', 'sort_order', 'is_active']),
            ]);

            return $updated;
        });
    }
}
