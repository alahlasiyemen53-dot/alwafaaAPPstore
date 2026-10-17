<?php

namespace App\Support;

use App\Contracts\TreasuryAccount;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Resolves the polymorphic treasury-account side of a TreasuryTransaction
 * or DriverCodSettlement (`account`, `destinationAccount`, `treasuryAccount`)
 * to one of the concrete account models it may legitimately be.
 */
final class TreasuryAccountResolver
{
    public static function resolve(?Model $model, string $notFoundMessage): Cashbox|BankAccount|ElectronicWalletAccount|TreasuryCurrencyAccount
    {
        if ($model instanceof Cashbox || $model instanceof BankAccount || $model instanceof ElectronicWalletAccount || $model instanceof TreasuryCurrencyAccount) {
            return $model;
        }

        throw new UnprocessableEntityHttpException($notFoundMessage);
    }
}
