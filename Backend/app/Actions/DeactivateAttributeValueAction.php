<?php

namespace App\Actions;

use App\Models\AttributeValue;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateAttributeValueAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(AttributeValue $value): AttributeValue
    {
        if (! $value->is_active) {
            throw new ConflictHttpException('قيمة الخاصية معطلة بالفعل.');
        }

        return DB::transaction(function () use ($value): AttributeValue {
            $updated = $this->attributes->setValueStatus($value, false);
            $this->auditLogger->record('admin.attribute_value.deactivated', $updated);

            return $updated;
        });
    }
}
