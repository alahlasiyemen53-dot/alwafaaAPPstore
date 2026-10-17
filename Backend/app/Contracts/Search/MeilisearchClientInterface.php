<?php

namespace App\Contracts\Search;

interface MeilisearchClientInterface
{
    public function isHealthy(): bool;

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $indexUid): array;

    /**
     * @return array<string, mixed>
     */
    public function createIndexIfNotExists(string $indexUid, string $primaryKey = 'id'): array;

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function updateSettings(string $indexUid, array $settings): array;

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<string, mixed>
     */
    public function addDocuments(string $indexUid, array $documents, ?string $primaryKey = 'id'): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteDocument(string $indexUid, string|int $documentId): array;

    /**
     * @param  array<int, string|int>  $documentIds
     * @return array<string, mixed>
     */
    public function deleteDocuments(string $indexUid, array $documentIds): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function search(string $indexUid, string $query, array $options = []): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteAllDocuments(string $indexUid): array;
}
