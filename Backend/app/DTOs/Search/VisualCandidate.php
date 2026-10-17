<?php

namespace App\DTOs\Search;

final class VisualCandidate
{
    public function __construct(
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly int $mediaId,
        public readonly float $similarity,
        public readonly string $vectorId,
    ) {}
}
