<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ActivateAttributeAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Attribute $attribute): Attribute
    {
        if ($attribute->is_active) {
            throw new ConflictHttpException('الخاصية نشطة بالفعل.');
        }

        return DB::transaction(function () use ($attribute): Attribute {
            $updated = $this->attributes->setStatus($attribute, true);
            $this->auditLogger->record('admin.attribute.activated', $updated);

            return $updated;
        });
    }
}
