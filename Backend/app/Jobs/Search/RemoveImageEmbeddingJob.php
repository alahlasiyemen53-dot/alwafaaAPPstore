<?php

namespace App\Jobs\Search;

use App\Services\Search\VisualIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RemoveImageEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $mediaId,
    ) {
        $this->onQueue('search');
    }

    public function handle(VisualIndexingService $indexingService): void
    {
        $indexingService->unindexMedia($this->mediaId);
    }
}
