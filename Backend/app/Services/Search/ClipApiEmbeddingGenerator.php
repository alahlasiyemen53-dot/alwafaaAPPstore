<?php

namespace App\Services\Search;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ClipApiEmbeddingGenerator implements EmbeddingGeneratorInterface
{
    private string $apiUrl;

    private ?string $apiKey;

    private float $timeout;

    private int $dimension;

    private string $modelVersion;

    public function __construct(
        ?string $apiUrl = null,
        ?string $apiKey = null,
        ?float $timeout = null,
        ?int $dimension = null,
        ?string $modelVersion = null,
    ) {
        $this->apiUrl = rtrim($apiUrl ?? (string) config('search.embedding.clip_api_url', ''), '/');
        $this->apiKey = $apiKey ?? config('search.embedding.clip_api_key');
        $this->timeout = $timeout ?? (float) config('search.embedding.timeout', 5.0);
        $this->dimension = $dimension ?? (int) config('search.vector.dimension', 512);
        $this->modelVersion = $modelVersion ?? (string) config('search.vector.model_version', 'clip-vit-base-patch32');
    }

    public function generateFromImagePath(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            throw new RuntimeException("Image file not found at: {$imagePath}");
        }

        try {
            $request = Http::timeout($this->timeout);
            if (! empty($this->apiKey)) {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post("{$this->apiUrl}/embed/image");

            if ($response->successful()) {
                /** @var array<int, float> $vector */
                $vector = $response->json('embedding') ?? $response->json();

                return $this->normalize($vector);
            }

            throw new RuntimeException("CLIP API responded with status {$response->status()}: {$response->body()}");
        } catch (Exception $e) {
            Log::error('CLIP API embedding generation failed: '.$e->getMessage());
            throw new RuntimeException("Failed to generate embedding via CLIP API: {$e->getMessage()}", 0, $e);
        }
    }

    public function generateFromUrl(string $url): array
    {
        try {
            $request = Http::timeout($this->timeout);
            if (! empty($this->apiKey)) {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->post("{$this->apiUrl}/embed/url", ['url' => $url]);

            if ($response->successful()) {
                /** @var array<int, float> $vector */
                $vector = $response->json('embedding') ?? $response->json();

                return $this->normalize($vector);
            }

            throw new RuntimeException("CLIP API url embedding failed: {$response->status()}");
        } catch (Exception $e) {
            Log::error('CLIP API URL embedding failed: '.$e->getMessage());
            throw new RuntimeException("Failed to generate embedding from URL: {$e->getMessage()}", 0, $e);
        }
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
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $v) {
            $norm += $v * $v;
        }

        $norm = sqrt($norm);
        if ($norm <= 0) {
            return $vector;
        }

        return array_map(fn ($v) => round($v / $norm, 6), $vector);
    }
}
