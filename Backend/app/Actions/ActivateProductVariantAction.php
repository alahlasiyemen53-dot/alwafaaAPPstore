<?php

namespace App\Actions;

use App\Enums\ProductVariantStatus;
use App\Models\ProductVariant;
use App\Repositories\ProductVariantRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ActivateProductVariantAction
{
    public function __construct(
        private readonly ProductVariantRepository $variants,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ProductVariant $variant): ProductVariant
    {
        if ($variant->status === ProductVariantStatus::Active) {
            throw new ConflictHttpException('المتغير نشط بالفعل.');
        }

        return DB::transaction(function () use ($variant): ProductVariant {
            $updated = $this->variants->transitionStatus($variant, ProductVariantStatus::Active);
            $this->auditLogger->record('admin.product_variant.activated', $updated);

            return $updated;
        });
    }
}
