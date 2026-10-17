<?php

namespace App\Jobs\Search;

use App\Services\Search\ProductIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RemoveProductFromSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $productId,
    ) {
        $this->onQueue('search');
    }

    public function handle(ProductIndexingService $indexingService): void
    {
        $indexingService->unindexProduct($this->productId);
    }
}
