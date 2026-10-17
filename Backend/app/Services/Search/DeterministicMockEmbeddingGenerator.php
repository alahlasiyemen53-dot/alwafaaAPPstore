<?php

namespace App\Services\Search;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use InvalidArgumentException;

final class DeterministicMockEmbeddingGenerator implements EmbeddingGeneratorInterface
{
    public function __construct(
        private readonly int $dimension = 512,
        private readonly string $modelVersion = 'mock-v1.0',
    ) {}

    public function generateFromImagePath(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            // Hash the path if file doesn't exist on disk (e.g., test fixture paths)
            return $this->generateVectorFromString($imagePath);
        }

        $content = file_get_contents($imagePath);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read image at: {$imagePath}");
        }

        return $this->generateVectorFromString($content);
    }

    public function generateFromUrl(string $url): array
    {
        return $this->generateVectorFromString($url);
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * Generate a deterministic L2-normalized float vector from any string content.
     *
     * @return array<int, float>
     */
    public function generateVectorFromString(string $seed): array
    {
        $vector = [];
        $hash = hash('sha256', $seed);
        $norm = 0.0;

        for ($i = 0; $i < $this->dimension; $i++) {
            // Generate deterministic pseudo-random float between -1.0 and 1.0
            $chunk = substr(hash('sha256', "{$hash}_{$i}"), 0, 8);
            $val = (hexdec($chunk) / 0xFFFFFFFF) * 2.0 - 1.0;
            $vector[] = (float) $val;
            $norm += $val * $val;
        }

        // L2 normalization: divide each component by sqrt(sum of squares)
        $norm = sqrt($norm);
        if ($norm > 0) {
            foreach ($vector as $idx => $val) {
                $vector[$idx] = round($val / $norm, 6);
            }
        }

        return $vector;
    }
}
