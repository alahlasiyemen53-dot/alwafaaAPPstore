<?php

namespace App\Actions;

use App\Data\CreateProductData;
use App\Enums\ProductVariantStatus;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Repositories\ProductRepository;
use App\Services\AuditLogger;
use App\Services\SkuGenerator;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateProductAction
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly AuditLogger $auditLogger,
        private readonly SkuGenerator $skuGenerator = new SkuGenerator,
    ) {}

    public function execute(CreateProductData $productData): Product
    {
        return $this->withCreateLock($productData->nameAr, function () use ($productData): Product {
            return DB::transaction(function () use ($productData): Product {
                if ($this->arabicNameExists($productData->nameAr)) {
                    throw new DomainException('يوجد منتج بنفس الاسم العربي مسبقاً. لم يتم إنشاء نسخة جديدة.');
                }

                $product = $this->products->create($productData);

                if (! $productData->hasVariants) {
                    $variant = ProductVariant::query()->create([
                        'product_id' => $product->id,
                        'sku' => $this->skuGenerator->generate(),
                        'status' => ProductVariantStatus::Active,
                        'is_default' => true,
                    ]);

                    $baseCurrency = Currency::query()->where('is_default', true)->first();
                    if ($baseCurrency !== null) {
                        VariantPrice::query()->create([
                            'product_variant_id' => $variant->id,
                            'currency_id' => $baseCurrency->id,
                            'price' => '0.0000',
                            'is_active' => true,
                        ]);
                    }
                }

                $this->auditLogger->record('admin.product.created', $product);

                return $product->load(['brand', 'category', 'variants']);
            });
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withCreateLock(string $nameAr, callable $callback): mixed
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $callback();
        }

        $lockKey = 'create_product_'.hash('sha256', mb_strtolower(trim($nameAr)));
        $acquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockKey])->acquired ?? 0);
        if ($acquired !== 1) {
            throw new DomainException('تعذر إكمال إنشاء المنتج حالياً. حاول مرة أخرى.');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockKey]);
        }
    }

    private function arabicNameExists(string $nameAr): bool
    {
        return Product::query()
            ->where('name_ar', trim($nameAr))
            ->exists();
    }
}
