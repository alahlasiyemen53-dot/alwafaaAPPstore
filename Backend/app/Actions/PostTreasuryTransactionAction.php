<?php

namespace App\Actions;

use App\Models\TreasuryTransaction;
use App\Services\TreasuryPostingService;

final class PostTreasuryTransactionAction
{
    public function __construct(
        private readonly TreasuryPostingService $postingService,
    ) {}

    public function execute(TreasuryTransaction $transaction): TreasuryTransaction
    {
        return $this->postingService->post($transaction);
    }
}
