<?php

namespace Tests\Feature;

use App\Actions\UpdateProductMerchandisingAction;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateProductMerchandisingActionTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->brand = Brand::query()->create(['name_ar' => 'براند', 'slug' => 'brand-1', 'is_active' => true]);
        $this->category = Category::query()->create(['name_ar' => 'تصنيف', 'slug' => 'cat-1', 'is_active' => true]);
    }

    public function test_merchandising_action_updates_product_and_writes_audit_log(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $product = Product::query()->create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'سيروم نضارة',
            'slug' => 'serum-glow',
            'is_best_seller' => false,
            'is_new' => false,
            'is_exclusive' => false,
        ]);

        $action = app(UpdateProductMerchandisingAction::class);
        $updated = $action->execute($product, isBestSeller: true, isNew: true, isExclusive: false);

        self::assertTrue($updated->is_best_seller);
        self::assertTrue($updated->is_new);
        self::assertFalse($updated->is_exclusive);

        $fresh = $product->fresh();
        self::assertTrue($fresh->is_best_seller);
        self::assertTrue($fresh->is_new);
        self::assertFalse($fresh->is_exclusive);

        $log = AuditLog::query()
            ->where('action', 'admin.product.merchandising_updated')
            ->where('subject_id', $product->id)
            ->first();

        self::assertNotNull($log);
        self::assertSame(false, $log->metadata['before']['is_best_seller']);
        self::assertSame(true, $log->metadata['after']['is_best_seller']);
        self::assertSame(false, $log->metadata['before']['is_new']);
        self::assertSame(true, $log->metadata['after']['is_new']);
    }
}
