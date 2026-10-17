<?php

namespace App\Jobs\Search;

use App\Services\Search\VisualIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class GenerateImageEmbeddingJob implements ShouldQueue
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
        /** @var Media|null $media */
        $media = Media::find($this->mediaId);

        if (! $media) {
            $indexingService->unindexMedia($this->mediaId);

            return;
        }

        $indexingService->indexMedia($media);
    }
}
