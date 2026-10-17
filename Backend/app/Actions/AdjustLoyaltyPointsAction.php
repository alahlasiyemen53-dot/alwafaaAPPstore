<?php

namespace App\Actions;

use App\DTOs\LoyaltyAdjustmentData;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Services\AuditLogger;
use App\Services\LoyaltyBalanceQuery;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AdjustLoyaltyPointsAction
{
    public function __construct(
        private readonly LoyaltyBalanceQuery $balanceQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(LoyaltyAdjustmentData $data): CustomerLoyaltyEntry
    {
        return DB::transaction(function () use ($data): CustomerLoyaltyEntry {
            /** @var CustomerLoyaltyAccount $account */
            $account = CustomerLoyaltyAccount::firstOrCreate(
                ['customer_id' => $data->customerId],
                ['status' => LoyaltyAccountStatus::Active]
            );

            $lockedAccount = CustomerLoyaltyAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (! $lockedAccount->isActive()) {
                throw new DomainException('حساب الولاء معلق ولا يمكن إجراء تسويات عليه.');
            }

            $isDebit = strtolower($data->type) === 'debit';
            $available = $this->balanceQuery->getAvailablePoints($lockedAccount);

            if ($isDebit && $data->points > $available) {
                throw new DomainException(sprintf(
                    'رصيد النقاط المتاح (%d) غير كافٍ لخصم (%d) نقطة.',
                    $available,
                    $data->points
                ));
            }

            $entryType = $isDebit ? LoyaltyEntryType::AdjustmentDebit : LoyaltyEntryType::AdjustmentCredit;
            $entryNumber = sprintf('LOY-ADJ-%d-%s', $lockedAccount->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerLoyaltyEntry $entry */
            $entry = CustomerLoyaltyEntry::create([
                'customer_loyalty_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => $entryType,
                'points' => $data->points,
                'source_type' => 'ManualAdjustment',
                'source_id' => (int) ($data->createdBy ?? 1),
                'source_event' => 'ADMIN_ADJUSTMENT',
                'description' => $data->reason,
                'occurred_at' => now(),
                'created_by' => $data->createdBy,
            ]);

            $this->auditLogger->record('admin.loyalty.adjusted', $entry, metadata: [
                'customer_id' => $data->customerId,
                'type' => $data->type,
                'points' => $data->points,
                'reason' => $data->reason,
                'entry_number' => $entryNumber,
                'balance_before' => $available,
                'balance_after' => $isDebit ? ($available - $data->points) : ($available + $data->points),
            ]);

            return $entry;
        });
    }
}
