<?php

namespace App\Actions;

use App\Models\CustomerTier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteCustomerTierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CustomerTier $tier): void
    {
        if ($tier->is_default) {
            throw ValidationException::withMessages([
                'tier' => 'لا يمكن حذف المستوى الافتراضي للنظام.',
            ]);
        }

        if ($tier->hasMembershipHistory()) {
            throw ValidationException::withMessages([
                'tier' => 'لا يمكن حذف المستوى لوجود سجل عضويات عملاء مرتبط به، يمكنك تعطيله بدلاً من ذلك.',
            ]);
        }

        DB::transaction(function () use ($tier): void {
            $metadata = [
                'id' => $tier->id,
                'code' => $tier->code,
                'name_ar' => $tier->name_ar,
            ];

            $tier->delete();

            $this->auditLogger->record(
                action: 'customer_tier.deleted',
                subject: null,
                metadata: $metadata,
            );
        });
    }
}
