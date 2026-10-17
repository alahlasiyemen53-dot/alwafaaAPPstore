<?php

namespace App\Actions;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateProductAction
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $this->assertTransition($product);
            $previousStatus = $product->status->value;
            $updated = $this->products->transition($product, ProductStatus::Inactive);
            $this->auditLogger->record('admin.product.deactivated', $updated, metadata: [
                'before' => $previousStatus,
                'after' => $updated->status->value,
            ]);

            return $updated;
        });
    }

    private function assertTransition(Product $product): void
    {
        if (! $product->status->canTransitionTo(ProductStatus::Inactive)) {
            throw new ConflictHttpException('The product is already inactive.');
        }
    }
}
