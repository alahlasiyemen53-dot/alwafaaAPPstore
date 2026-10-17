<?php

namespace App\Contracts\Search;

use App\DTOs\Search\VisualSearchResult;

interface VisualProductSearchInterface
{
    /**
     * @param  string  $imagePathOrUrl  Local path or remote URL to query image
     */
    public function searchByImage(string $imagePathOrUrl, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult;

    /**
     * @param  array<int, float>  $vector
     */
    public function searchByVector(array $vector, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult;
}
