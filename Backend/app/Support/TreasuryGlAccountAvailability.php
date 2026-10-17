<?php

namespace App\Support;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;

/**
 * GL Asset accounts available for treasury parents (cashbox / bank / wallet).
 * The same chart account may back multiple parents; operational balances stay
 * separated via treasury_currency_account_id on journal lines.
 */
final class TreasuryGlAccountAvailability
{
    /**
     * Active, postable Asset GL accounts for treasury binding.
     *
     * @return Collection<int, Account>
     */
    public static function availableAssetAccounts(?int $exceptGlAccountId = null): Collection
    {
        // Cross-parent sharing is allowed; exceptGlAccountId is retained for
        // call-site compatibility and does not exclude other accounts.
        unset($exceptGlAccountId);

        return Account::where('is_active', true)
            ->where('is_postable', true)
            ->where('type', AccountType::Asset)
            ->orderBy('code')
            ->get();
    }

    /**
     * @deprecated Cross-parent GL sharing is allowed; always returns false.
     */
    public static function isInUse(
        int $glAccountId,
        ?int $exceptGlAccountId = null,
        ?string $sameParentType = null,
        ?int $sameParentId = null,
    ): bool {
        unset($glAccountId, $exceptGlAccountId, $sameParentType, $sameParentId);

        return false;
    }
}
