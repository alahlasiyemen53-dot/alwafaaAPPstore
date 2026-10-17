<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Text Search Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "meilisearch", "null"
    |
    */
    'default_text_driver' => env('SEARCH_TEXT_DRIVER', 'meilisearch'),

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    */
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY', null),
        'index_prefix' => env('MEILISEARCH_INDEX_PREFIX', 'storeapp_catalog_'),
        'timeout' => (float) env('MEILISEARCH_TIMEOUT', 2.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store Configuration
    |--------------------------------------------------------------------------
    |
    | Supported: "database_cosine", "meilisearch"
    |
    */
    'vector' => [
        'driver' => env('SEARCH_VECTOR_DRIVER', 'database_cosine'),
        'dimension' => (int) env('SEARCH_VECTOR_DIMENSION', 512),
        'model_version' => env('SEARCH_VECTOR_MODEL', 'clip-vit-base-patch32'),
        'similarity_threshold' => (float) env('SEARCH_VECTOR_THRESHOLD', 0.60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Embedding Generator Configuration
    |--------------------------------------------------------------------------
    |
    | Supported: "mock", "clip_api"
    |
    */
    'embedding' => [
        'driver' => env('SEARCH_EMBEDDING_DRIVER', 'mock'),
        'clip_api_url' => env('CLIP_API_URL', null),
        'clip_api_key' => env('CLIP_API_KEY', null),
        'timeout' => (float) env('CLIP_API_TIMEOUT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search & Indexing Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
        'max_visual_candidates' => 50,
        'reindex_chunk_size' => 100,
    ],

];
