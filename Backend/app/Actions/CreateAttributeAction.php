<?php

namespace App\Actions;

use App\Data\AttributeData;
use App\Models\Attribute;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateAttributeAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(AttributeData $data): Attribute
    {
        return DB::transaction(function () use ($data): Attribute {
            $attribute = $this->attributes->create($data);
            $this->auditLogger->record('admin.attribute.created', $attribute);

            return $attribute;
        });
    }
}
