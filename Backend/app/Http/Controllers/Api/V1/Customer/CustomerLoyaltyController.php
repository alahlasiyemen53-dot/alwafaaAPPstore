<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Loyalty\CustomerLoyaltyResource;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Services\LoyaltyBalanceQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerLoyaltyController extends Controller
{
    public function show(Request $request, LoyaltyBalanceQuery $balanceQuery): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = $request->user('customer_jwt');

        if (! $customer instanceof Customer) {
            return ApiResponse::error(
                message: ApiErrorCode::AuthRequired->defaultMessage(),
                code: ApiErrorCode::AuthRequired,
                status: 401
            );
        }

        /** @var CustomerLoyaltyAccount|null $loyaltyAccount */
        $loyaltyAccount = $customer->loyaltyAccount()->first();

        $availablePoints = 0;
        $lifetimeEarned = 0;
        $lifetimeRedeemed = 0;
        $recentEntries = [];

        if ($loyaltyAccount !== null) {
            $calculation = $balanceQuery->calculate($loyaltyAccount);
            $availablePoints = $calculation['available_points'];
            $lifetimeEarned = $calculation['lifetime_earned'];
            $lifetimeRedeemed = $calculation['lifetime_redeemed'];

            $recentEntries = CustomerLoyaltyEntry::query()
                ->where('customer_loyalty_account_id', $loyaltyAccount->id)
                ->orderBy('occurred_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->all();
        }

        $currentTier = $customer->getCurrentTier();

        return ApiResponse::success(
            new CustomerLoyaltyResource([
                'available_points' => $availablePoints,
                'lifetime_earned' => $lifetimeEarned,
                'lifetime_redeemed' => $lifetimeRedeemed,
                'current_tier' => $currentTier,
                'recent_entries' => $recentEntries,
            ]),
            'تم استرجاع بيانات الولاء بنجاح.'
        );
    }
}
