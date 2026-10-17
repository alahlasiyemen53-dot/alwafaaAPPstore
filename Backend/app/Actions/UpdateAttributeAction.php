<?php

namespace App\Actions;

use App\Data\AttributeData;
use App\Models\Attribute;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateAttributeAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Attribute $attribute, AttributeData $data): Attribute
    {
        return DB::transaction(function () use ($attribute, $data): Attribute {
            $before = $attribute->only(['name_ar', 'name_en', 'code', 'sort_order', 'is_active']);
            $updated = $this->attributes->update($attribute, $data);
            $this->auditLogger->record('admin.attribute.updated', $updated, metadata: [
                'before' => $before,
                'after' => $updated->only(['name_ar', 'name_en', 'code', 'sort_order', 'is_active']),
            ]);

            return $updated;
        });
    }
}
