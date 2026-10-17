<?php

namespace App\Actions;

use App\Enums\CustomerTierAssignmentSource;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignCustomerTierAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        Customer $customer,
        CustomerTier $targetTier,
        ?string $reason = null,
        ?User $assignedBy = null,
        CustomerTierAssignmentSource $source = CustomerTierAssignmentSource::ADMIN,
    ): CustomerTierMembership {
        return DB::transaction(function () use ($customer, $targetTier, $reason, $assignedBy, $source): CustomerTierMembership {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $targetTier->is_active) {
                throw ValidationException::withMessages([
                    'tier_id' => 'المستوى المحدد غير مفعل ولا يمكن التعيين إليه.',
                ]);
            }

            if ($lockedCustomer->current_tier_id === $targetTier->id) {
                throw ValidationException::withMessages([
                    'tier_id' => 'العميل ينتمي بالفعل إلى هذا المستوى.',
                ]);
            }

            $trimmedReason = $reason !== null ? trim($reason) : null;
            if ($source === CustomerTierAssignmentSource::ADMIN && (empty($trimmedReason) || mb_strlen($trimmedReason) < 5)) {
                throw ValidationException::withMessages([
                    'reason' => 'سبب تغيير المستوى إلزامي (5 أحرف على الأقل).',
                ]);
            }

            $actor = $assignedBy ?? (auth()->check() ? auth()->user() : null);
            $oldTierId = $lockedCustomer->current_tier_id;
            $now = now();

            // Close existing active memberships
            $activeMemberships = CustomerTierMembership::query()
                ->where('customer_id', $lockedCustomer->id)
                ->whereNull('ends_at')
                ->lockForUpdate()
                ->get();

            foreach ($activeMemberships as $activeMembership) {
                $activeMembership->update(['ends_at' => $now]);
            }

            // Create new active membership
            $newMembership = CustomerTierMembership::query()->create([
                'customer_id' => $lockedCustomer->id,
                'customer_tier_id' => $targetTier->id,
                'starts_at' => $now,
                'ends_at' => null,
                'assignment_source' => $source,
                'assigned_by' => $actor?->id,
                'change_reason' => $trimmedReason,
            ]);

            $lockedCustomer->update(['current_tier_id' => $targetTier->id]);

            $this->auditLogger->record(
                action: 'customer_tier_membership.changed',
                subject: $lockedCustomer,
                metadata: [
                    'customer_id' => $lockedCustomer->id,
                    'old_tier_id' => $oldTierId,
                    'new_tier_id' => $targetTier->id,
                    'source' => $source->value,
                    'reason' => $trimmedReason,
                    'assigned_by' => $actor?->id,
                ],
                actor: $actor,
            );

            return $newMembership;
        });
    }
}
