<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use Illuminate\Database\Eloquent\Model;

/**
 * Opens additional currency slots on a treasury parent, all sharing parent GL.
 */
final class TreasuryMultiCurrencySync
{
    /**
     * Ensure currency accounts exist for every id in $currencyIds (parent boot
     * already creates the primary currency). Extra currencies reuse parent GL.
     *
     * @param  list<int>  $currencyIds
     */
    public static function ensureCurrencyAccounts(Model $parent, array $currencyIds, ?int $userId = null): void
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $currencyIds)));
        if ($uniqueIds === []) {
            return;
        }

        $resolvedType = get_class($parent);
        $glAccountId = (int) $parent->getAttribute('gl_account_id');
        $existing = TreasuryCurrencyAccount::query()
            ->where('accountable_type', $resolvedType)
            ->where('accountable_id', $parent->getKey())
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($uniqueIds as $currencyId) {
            if (in_array($currencyId, $existing, true)) {
                continue;
            }

            TreasuryCurrencyAccount::query()->create([
                'accountable_type' => $resolvedType,
                'accountable_id' => $parent->getKey(),
                'currency_id' => $currencyId,
                'gl_account_id' => $glAccountId,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /**
     * @param  list<int>  $currencyIds
     * @return array{0: int, 1: list<int>} primaryCurrencyId, ordered unique ids
     */
    public static function normalizeCurrencyIds(array $currencyIds): array
    {
        $unique = [];
        foreach ($currencyIds as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $unique, true)) {
                $unique[] = $id;
            }
        }

        if ($unique === []) {
            throw new \InvalidArgumentException('يجب اختيار عملة واحدة على الأقل.');
        }

        return [$unique[0], $unique];
    }

    /**
     * Merge requested currency ids with already-opened ones. Keep the parent's
     * primary currency unless the parent still has a single currency slot.
     *
     * @param  list<int>  $currencyIds
     * @param  list<int>  $existingIds
     * @return array{0: int, 1: list<int>, 2: bool} primaryId, mergedIds, primaryChanging
     */
    public static function resolveUpdateCurrencySelection(
        int $currentPrimaryId,
        array $currencyIds,
        array $existingIds,
    ): array {
        [, $requestedIds] = self::normalizeCurrencyIds($currencyIds);
        $existingIds = array_values(array_unique(array_map('intval', $existingIds)));
        $normalizedIds = array_values(array_unique([...$existingIds, ...$requestedIds]));

        $primaryCurrencyId = $currentPrimaryId;
        if (count($existingIds) <= 1 && $requestedIds[0] !== $primaryCurrencyId) {
            $primaryCurrencyId = $requestedIds[0];
        }

        return [$primaryCurrencyId, $normalizedIds, $primaryCurrencyId !== $currentPrimaryId];
    }
}
