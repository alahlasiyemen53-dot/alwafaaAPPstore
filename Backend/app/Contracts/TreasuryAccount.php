<?php

namespace App\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared contract implemented by every concrete treasury account model
 * (Cashbox, BankAccount, ElectronicWalletAccount) that can be the
 * polymorphic target of a TreasuryTransaction or DriverCodSettlement.
 *
 * @property-read int $id
 * @property-read bool $is_active
 * @property-read int $currency_id
 * @property-read string $displayName
 */
interface TreasuryAccount
{
    public function currency(): BelongsTo;

    public function glAccount(): BelongsTo;

    public function calculateBalance(?Carbon $asOf = null): string;
}
