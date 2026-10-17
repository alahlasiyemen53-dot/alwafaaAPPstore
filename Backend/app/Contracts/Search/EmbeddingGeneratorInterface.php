<?php

namespace App\Contracts\Search;

interface EmbeddingGeneratorInterface
{
    /**
     * Generate normalized vector embedding from local image file path.
     *
     * @return array<int, float>
     */
    public function generateFromImagePath(string $imagePath): array;

    /**
     * Generate normalized vector embedding from image URL.
     *
     * @return array<int, float>
     */
    public function generateFromUrl(string $url): array;

    public function getModelVersion(): string;

    public function getDimension(): int;
}
