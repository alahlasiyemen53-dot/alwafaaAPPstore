<?php

namespace App\Actions;

use App\Models\CustomerTier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerTierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(CustomerTier $tier, array $data): CustomerTier
    {
        $newCode = isset($data['code']) ? Str::upper(trim($data['code'])) : $tier->code;

        if ($newCode !== $tier->code) {
            if ($tier->hasMembershipHistory()) {
                throw ValidationException::withMessages([
                    'code' => 'لا يمكن تعديل كود المستوى بعد استخدامه في تاريخ عضويات العملاء.',
                ]);
            }

            if (CustomerTier::query()->where('code', $newCode)->where('id', '!=', $tier->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'كود المستوى مستخدم بالفعل في مستوى آخر.',
                ]);
            }
        }

        $wasDefault = (bool) $tier->is_default;
        $isDefault = isset($data['is_default']) ? (bool) $data['is_default'] : $wasDefault;

        if ($wasDefault && ! $isDefault) {
            throw ValidationException::withMessages([
                'is_default' => 'لا يمكن إلغاء تعيين المستوى الافتراضي بدون تعيين مستوى افتراضي بديل أولاً.',
            ]);
        }

        $wasActive = (bool) $tier->is_active;
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $wasActive;

        if ($isDefault && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'لا يمكن تعطيل المستوى الافتراضي للنظام.',
            ]);
        }

        return DB::transaction(function () use ($tier, $data, $newCode, $isDefault, $isActive, $wasActive): CustomerTier {
            if ($isDefault && ! $tier->is_default) {
                CustomerTier::query()->where('id', '!=', $tier->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $oldValues = $tier->only(['code', 'name_ar', 'name_en', 'description', 'is_active', 'is_default', 'sort_order']);

            $tier->update([
                'code' => $newCode,
                'name_ar' => trim($data['name_ar'] ?? $tier->name_ar),
                'name_en' => isset($data['name_en']) ? trim($data['name_en']) : $tier->name_en,
                'description' => isset($data['description']) ? trim($data['description']) : $tier->description,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $tier->sort_order,
            ]);

            $this->auditLogger->record(
                action: 'customer_tier.updated',
                subject: $tier,
                metadata: [
                    'id' => $tier->id,
                    'code' => $tier->code,
                ],
                oldValues: $oldValues,
            );

            if ($wasActive && ! $isActive) {
                $this->auditLogger->record(
                    action: 'customer_tier.deactivated',
                    subject: $tier,
                    metadata: ['code' => $tier->code],
                );
            } elseif (! $wasActive && $isActive) {
                $this->auditLogger->record(
                    action: 'customer_tier.activated',
                    subject: $tier,
                    metadata: ['code' => $tier->code],
                );
            }

            return $tier;
        });
    }
}
