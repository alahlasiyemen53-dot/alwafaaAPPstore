<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MeilisearchHttpClient implements MeilisearchClientInterface
{
    private string $host;

    private ?string $key;

    private float $timeout;

    private ?bool $healthyCached = null;

    public function __construct(?string $host = null, ?string $key = null, ?float $timeout = null)
    {
        $this->host = rtrim($host ?? (string) config('search.meilisearch.host', 'http://127.0.0.1:7700'), '/');
        $this->key = $key ?? config('search.meilisearch.key');
        $this->timeout = $timeout ?? (float) config('search.meilisearch.timeout', 2.0);
    }

    public function isHealthy(): bool
    {
        if ($this->healthyCached !== null) {
            return $this->healthyCached;
        }

        try {
            $response = Http::timeout(min($this->timeout, 0.5))
                ->withHeaders($this->getHeaders())
                ->get("{$this->host}/health");

            $this->healthyCached = $response->successful() && ($response->json('status') === 'available');
        } catch (Exception $e) {
            Log::debug('Meilisearch health check failed: '.$e->getMessage());
            $this->healthyCached = false;
        }

        return $this->healthyCached;
    }

    public function getStats(string $indexUid): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->get("{$this->host}/indexes/{$indexUid}/stats");

            if ($response->successful()) {
                return (array) $response->json();
            }
        } catch (Exception $e) {
            Log::warning("Failed to get Meilisearch stats for {$indexUid}: ".$e->getMessage());
        }

        return [
            'numberOfDocuments' => 0,
            'isIndexing' => false,
            'fieldDistribution' => [],
        ];
    }

    public function createIndexIfNotExists(string $indexUid, string $primaryKey = 'id'): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->host}/indexes", [
                    'uid' => $indexUid,
                    'primaryKey' => $primaryKey,
                ]);

            return (array) $response->json();
        } catch (Exception $e) {
            Log::warning("Failed to create Meilisearch index {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function updateSettings(string $indexUid, array $settings): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->patch("{$this->host}/indexes/{$indexUid}/settings", $settings);

            return (array) $response->json();
        } catch (Exception $e) {
            Log::error("Failed to update Meilisearch settings for {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function addDocuments(string $indexUid, array $documents, ?string $primaryKey = 'id'): array
    {
        if (empty($documents)) {
            return ['status' => 'empty'];
        }

        try {
            $url = "{$this->host}/indexes/{$indexUid}/documents";
            if ($primaryKey !== null) {
                $url .= "?primaryKey={$primaryKey}";
            }

            $response = Http::timeout(max($this->timeout * 3, 5.0))
                ->withHeaders($this->getHeaders())
                ->post($url, $documents);

            return (array) $response->json();
        } catch (Exception $e) {
            Log::error("Failed to add documents to Meilisearch index {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function deleteDocument(string $indexUid, string|int $documentId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete("{$this->host}/indexes/{$indexUid}/documents/{$documentId}");

            return (array) $response->json();
        } catch (Exception $e) {
            Log::warning("Failed to delete document {$documentId} from {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function deleteDocuments(string $indexUid, array $documentIds): array
    {
        if (empty($documentIds)) {
            return ['status' => 'empty'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->host}/indexes/{$indexUid}/documents/delete-batch", array_values($documentIds));

            return (array) $response->json();
        } catch (Exception $e) {
            Log::error("Failed to delete batch documents from {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function search(string $indexUid, string $query, array $options = []): array
    {
        try {
            $payload = array_merge(['q' => $query], $options);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->host}/indexes/{$indexUid}/search", $payload);

            if ($response->successful()) {
                return (array) $response->json();
            }

            Log::warning("Meilisearch search non-200 for index {$indexUid}: ".$response->body());
        } catch (Exception $e) {
            Log::warning("Meilisearch search error on index {$indexUid}: ".$e->getMessage());
        }

        return [
            'hits' => [],
            'query' => $query,
            'processingTimeMs' => 0,
            'limit' => $options['limit'] ?? 20,
            'offset' => $options['offset'] ?? 0,
            'estimatedTotalHits' => 0,
        ];
    }

    public function deleteAllDocuments(string $indexUid): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete("{$this->host}/indexes/{$indexUid}/documents");

            return (array) $response->json();
        } catch (Exception $e) {
            Log::error("Failed to delete all documents from {$indexUid}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($this->key)) {
            $headers['Authorization'] = "Bearer {$this->key}";
        }

        return $headers;
    }
}
