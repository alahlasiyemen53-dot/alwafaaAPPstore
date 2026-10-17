<?php

namespace App\Actions;

use App\Models\TreasuryTransaction;
use App\Services\TreasuryPostingService;

final class ReverseTreasuryTransactionAction
{
    public function __construct(
        private readonly TreasuryPostingService $postingService,
    ) {}

    public function execute(TreasuryTransaction $transaction, string $reason): TreasuryTransaction
    {
        return $this->postingService->reverse($transaction, $reason);
    }
}
