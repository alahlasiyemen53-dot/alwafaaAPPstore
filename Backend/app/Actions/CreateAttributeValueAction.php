<?php

namespace App\Actions;

use App\Data\AttributeValueData;
use App\Models\AttributeValue;
use App\Repositories\AttributeRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateAttributeValueAction
{
    public function __construct(
        private readonly AttributeRepository $attributes,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(AttributeValueData $data): AttributeValue
    {
        return DB::transaction(function () use ($data): AttributeValue {
            $value = $this->attributes->createValue($data);
            $this->auditLogger->record('admin.attribute_value.created', $value);

            return $value;
        });
    }
}
