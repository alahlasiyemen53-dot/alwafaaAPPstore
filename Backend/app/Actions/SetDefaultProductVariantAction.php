<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SetDefaultProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepository $variants,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant): ProductVariant
    {
        if ($variant->is_default) {
            throw new ConflictHttpException('هذا المتغير هو الافتراضي بالفعل.');
        }

        return DB::transaction(function () use ($variant): ProductVariant {
            $updated = $this->variants->setDefault($variant);
            $this->auditLogger->record('admin.product_variant.set_default', $updated, metadata: [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
            ]);

            return $updated;
        });
    }
}
