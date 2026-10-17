<?php

namespace App\Actions;

use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEntryType;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\LoyaltyCalculationService;
use App\Support\RaceSafeFirstOrCreate;
use Illuminate\Support\Facades\DB;

final class EarnLoyaltyPointsAction
{
    public function __construct(
        private readonly LoyaltyCalculationService $calculationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Order $order, int $userId = 0): ?CustomerLoyaltyEntry
    {
        return DB::transaction(function () use ($order, $userId): ?CustomerLoyaltyEntry {
            // Find or create customer loyalty account. Two concurrent first-time earns for the
            // same new customer can both race the create; RaceSafeFirstOrCreate recovers by
            // re-reading the winning row after the unique-constraint loser fails.
            $account = RaceSafeFirstOrCreate::resolve(
                CustomerLoyaltyAccount::class,
                ['customer_id' => $order->customer_id],
                ['status' => LoyaltyAccountStatus::Active]
            );

            $lockedAccount = CustomerLoyaltyAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if (! $lockedAccount->isActive()) {
                return null;
            }

            // Check idempotency: check if already earned for this order
            $existing = CustomerLoyaltyEntry::where('customer_loyalty_account_id', $lockedAccount->id)
                ->where('source_type', 'Order')
                ->where('source_id', $order->id)
                ->where('source_event', 'DELIVERY_DELIVERED')
                ->first();

            if ($existing) {
                return $existing;
            }

            $points = $this->calculationService->calculateOrderPoints($order);
            if ($points <= 0) {
                return null;
            }

            $entryNumber = sprintf('LOY-%d-%s', $order->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerLoyaltyEntry $entry */
            $entry = CustomerLoyaltyEntry::create([
                'customer_loyalty_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => LoyaltyEntryType::Earn,
                'points' => $points,
                'source_type' => 'Order',
                'source_id' => $order->id,
                'source_event' => 'DELIVERY_DELIVERED',
                'description' => sprintf('نقاط مكتسبة عن تسليم الطلب رقم %s', $order->order_number),
                'occurred_at' => now(),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            $this->auditLogger->record('admin.loyalty.points_earned', $entry, metadata: [
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'points' => $points,
                'entry_number' => $entryNumber,
            ]);

            return $entry;
        });
    }
}
