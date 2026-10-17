<?php

namespace App\Actions;

use App\Models\CustomerTier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateCustomerTierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data): CustomerTier
    {
        $code = Str::upper(trim($data['code'] ?? ''));
        if (empty($code)) {
            throw ValidationException::withMessages([
                'code' => 'كود المستوى مطلوب.',
            ]);
        }

        if (CustomerTier::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'كود المستوى مستخدم بالفعل.',
            ]);
        }

        return DB::transaction(function () use ($data, $code): CustomerTier {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                CustomerTier::query()->where('is_default', true)->update(['is_default' => false]);
            }

            /** @var CustomerTier $tier */
            $tier = CustomerTier::query()->create([
                'code' => $code,
                'name_ar' => trim($data['name_ar']),
                'name_en' => ! empty($data['name_en']) ? trim($data['name_en']) : null,
                'description' => ! empty($data['description']) ? trim($data['description']) : null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_default' => $isDefault,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->auditLogger->record(
                action: 'customer_tier.created',
                subject: $tier,
                metadata: [
                    'code' => $tier->code,
                    'name_ar' => $tier->name_ar,
                    'is_default' => $tier->is_default,
                    'is_active' => $tier->is_active,
                ],
            );

            return $tier;
        });
    }
}
