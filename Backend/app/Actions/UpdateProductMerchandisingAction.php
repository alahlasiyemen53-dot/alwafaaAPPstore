<?php

namespace App\Actions;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateProductMerchandisingAction
{
    private const MERCHANDISING_FIELDS = [
        'is_best_seller',
        'is_new',
        'is_exclusive',
    ];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product, bool $isBestSeller, bool $isNew, bool $isExclusive): Product
    {
        return DB::transaction(function () use ($product, $isBestSeller, $isNew, $isExclusive): Product {
            $before = [
                'is_best_seller' => (bool) $product->is_best_seller,
                'is_new' => (bool) $product->is_new,
                'is_exclusive' => (bool) $product->is_exclusive,
            ];
            $updated = $this->products->updateMerchandising($product, $isBestSeller, $isNew, $isExclusive);
            $after = [
                'is_best_seller' => (bool) $updated->is_best_seller,
                'is_new' => (bool) $updated->is_new,
                'is_exclusive' => (bool) $updated->is_exclusive,
            ];

            $this->auditLogger->record(
                'admin.product.merchandising_updated',
                $updated,
                metadata: compact('before', 'after')
            );

            return $updated;
        });
    }
}
