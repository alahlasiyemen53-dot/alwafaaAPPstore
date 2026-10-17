<?php

namespace Tests\Feature;

use App\Actions\ActivateProductAction;
use App\Actions\CreateProductAction;
use App\Actions\DeactivateProductAction;
use App\Actions\UpdateProductAction;
use App\Data\CreateProductData;
use App\Data\UpdateProductData;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class ProductActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles([Role::query()->where('name', 'admin')->firstOrFail()]);
        $this->actingAs($admin);
    }

    public function test_create_action_persists_relations_and_actor_audit(): void
    {
        [$brand, $category] = $this->catalogDimensions();
        $product = app(CreateProductAction::class)->execute(new CreateProductData(
            $brand->id, $category->id, 'أحمر شفاه', 'Lipstick', 'lipstick',
            'وصف عربي', 'English description', ProductStatus::Draft, true,
        ));

        self::assertTrue($product->brand->is($brand));
        self::assertTrue($product->category->is($category));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.product.created', 'subject_id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.product.created', 'actor_id' => auth()->id()]);
    }

    public function test_update_action_preserves_slug_and_audits_mutable_product_changes(): void
    {
        $product = $this->product(['brand_id' => $this->catalogDimensions()[0]->id]);
        $originalSlug = $product->slug;
        $updated = app(UpdateProductAction::class)->execute($product, new UpdateProductData(
            $product->brand_id, $product->category_id, 'أحمر شفاه مطفي', null, 'matte-lipstick', null, null, false,
        ));

        self::assertSame($originalSlug, $updated->slug);
        self::assertSame('أحمر شفاه مطفي', $updated->name_ar);
        self::assertSame($product->brand_id, $updated->brand_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.product.updated', 'subject_id' => $product->id]);
    }

    public function test_lifecycle_actions_change_status_and_record_previous_state(): void
    {
        $product = $this->product(['status' => ProductStatus::Draft]);
        app(ActivateProductAction::class)->execute($product);
        app(DeactivateProductAction::class)->execute($product->fresh());

        self::assertSame(ProductStatus::Inactive, $product->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.product.activated']);
        $metadata = AuditLog::query()->where('action', 'admin.product.activated')->value('metadata');
        self::assertSame(['before' => 'draft', 'after' => 'active'], $metadata);
    }

    public function test_repeating_a_lifecycle_transition_is_rejected(): void
    {
        $product = $this->product(['status' => ProductStatus::Active]);

        $this->expectException(ConflictHttpException::class);
        app(ActivateProductAction::class)->execute($product);
    }

    public function test_database_unique_constraint_rejects_duplicate_product_slug(): void
    {
        $this->product(['slug' => 'unique-product']);

        $this->expectException(QueryException::class);
        $this->product(['slug' => 'unique-product']);
    }

    public function test_database_foreign_key_prevents_deleting_an_assigned_category(): void
    {
        $product = $this->product();

        $this->expectException(QueryException::class);
        $product->category()->firstOrFail()->delete();
    }

    private function catalogDimensions(): array
    {
        return [
            Brand::query()->firstOrCreate(['slug' => 'lamma'], ['name_ar' => 'لمّة', 'is_active' => true]),
            Category::query()->firstOrCreate(['slug' => 'makeup'], ['name_ar' => 'مكياج', 'sort_order' => 1, 'is_active' => true]),
        ];
    }

    private function product(array $overrides = []): Product
    {
        [, $category] = $this->catalogDimensions();

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'منتج تجريبي',
            'slug' => 'sample-product',
            'status' => ProductStatus::Draft,
            'has_variants' => false,
        ], $overrides));
    }
}
