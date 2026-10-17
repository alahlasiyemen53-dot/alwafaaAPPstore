<?php

namespace App\Actions;

use App\Enums\LoyaltyEntryType;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Services\AuditLogger;
use App\Services\LoyaltyBalanceQuery;
use App\Services\LoyaltyCalculationService;
use Illuminate\Support\Facades\DB;

final class ClawbackLoyaltyPointsAction
{
    public function __construct(
        private readonly LoyaltyCalculationService $calculationService,
        private readonly LoyaltyBalanceQuery $balanceQuery,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(SalesReturn $salesReturn, int $userId = 0): ?CustomerLoyaltyEntry
    {
        return DB::transaction(function () use ($salesReturn, $userId): ?CustomerLoyaltyEntry {
            $order = Order::query()->find($salesReturn->order_id);
            if (! $order) {
                return null;
            }

            $account = CustomerLoyaltyAccount::where('customer_id', $order->customer_id)->first();
            if (! $account || ! $account->isActive()) {
                return null;
            }

            $lockedAccount = CustomerLoyaltyAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            // Idempotency: check if already clawed back for this return
            $existing = CustomerLoyaltyEntry::where('customer_loyalty_account_id', $lockedAccount->id)
                ->where('source_type', 'SalesReturn')
                ->where('source_id', $salesReturn->id)
                ->where('source_event', 'SALES_RETURN_POSTED')
                ->first();

            if ($existing) {
                return $existing;
            }

            // Calculate earned points from original order
            $earnedPoints = (int) CustomerLoyaltyEntry::where('customer_loyalty_account_id', $lockedAccount->id)
                ->where('source_type', 'Order')
                ->where('source_id', $order->id)
                ->where('type', LoyaltyEntryType::Earn)
                ->sum('points');

            if ($earnedPoints <= 0) {
                return null;
            }

            // Check previous clawbacks on this order
            $siblingReturnIds = SalesReturn::where('order_id', $order->id)->pluck('id')->all();
            $alreadyClawedBack = (int) CustomerLoyaltyEntry::where('customer_loyalty_account_id', $lockedAccount->id)
                ->where('source_type', 'SalesReturn')
                ->whereIn('source_id', $siblingReturnIds)
                ->where('type', LoyaltyEntryType::Clawback)
                ->sum('points');

            $remainingClawbackable = max(0, $earnedPoints - $alreadyClawedBack);
            if ($remainingClawbackable <= 0) {
                return null;
            }

            $orderBaseTotal = (string) ($order->base_currency_total ?? $order->total);
            $returnBaseTotal = (string) ($salesReturn->base_currency_total ?? $salesReturn->total);

            $pointsToClawback = $this->calculationService->calculateReturnClawbackPoints(
                orderBaseTotal: $orderBaseTotal,
                returnBaseTotal: $returnBaseTotal,
                earnedPoints: $earnedPoints
            );

            $pointsToClawback = min($pointsToClawback, $remainingClawbackable);

            // Non-negative invariant: cannot claw back more than available points
            $available = $this->balanceQuery->getAvailablePoints($lockedAccount);
            $pointsToClawback = min($pointsToClawback, $available);

            if ($pointsToClawback <= 0) {
                return null;
            }

            $entryNumber = sprintf('LOY-RET-%d-%s', $salesReturn->id, strtoupper(bin2hex(random_bytes(4))));

            /** @var CustomerLoyaltyEntry $entry */
            $entry = CustomerLoyaltyEntry::create([
                'customer_loyalty_account_id' => $lockedAccount->id,
                'entry_number' => $entryNumber,
                'type' => LoyaltyEntryType::Clawback,
                'points' => $pointsToClawback,
                'source_type' => 'SalesReturn',
                'source_id' => $salesReturn->id,
                'source_event' => 'SALES_RETURN_POSTED',
                'description' => sprintf('استرداد نقاط ولاء لمرتجع مبيعات رقم %s', $salesReturn->return_number),
                'occurred_at' => now(),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            $this->auditLogger->record('admin.loyalty.points_clawed_back', $entry, metadata: [
                'customer_id' => $order->customer_id,
                'sales_return_id' => $salesReturn->id,
                'order_id' => $order->id,
                'points_clawed_back' => $pointsToClawback,
                'entry_number' => $entryNumber,
            ]);

            return $entry;
        });
    }
}
