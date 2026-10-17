<?php

namespace App\Contracts\Search;

use App\DTOs\Search\ProductSearchQuery;
use App\DTOs\Search\ProductSearchResult;

interface ProductSearchInterface
{
    public function search(ProductSearchQuery $query): ProductSearchResult;

    public function isHealthy(): bool;
}
