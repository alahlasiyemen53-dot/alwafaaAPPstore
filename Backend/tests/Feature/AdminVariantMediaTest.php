<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class AdminVariantMediaTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(AuthorizationSeeder::class);

        $brand = Brand::query()->create(['name_ar' => 'براند لمّة', 'slug' => 'brand-lamma', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'العناية', 'slug' => 'care', 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'سيروم الشعر',
            'name_en' => 'Hair Serum',
            'slug' => 'hair-serum',
            'status' => 'active',
            'has_variants' => true,
        ]);

        $this->variant = ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'sku' => 'SERUM-50ML',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_guest_cannot_upload_variant_media(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 600, 600);
        $this->post("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media", ['file' => $file])
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_upload_variant_media(): void
    {
        $user = $this->userWithRole('customer_support');
        $file = UploadedFile::fake()->image('test.jpg', 600, 600);

        $this->actingAs($user)
            ->post("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media", ['file' => $file])
            ->assertForbidden();
    }

    public function test_authorized_admin_uploads_variant_media_and_generates_webp_conversions(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->image('serum-50.jpg', 1200, 1200);

        $this->actingAs($admin)
            ->post("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media", [
                'file' => $file,
            ])
            ->assertRedirect();

        $this->variant->refresh();
        self::assertSame(1, $this->variant->getMedia('variant_images')->count());

        /** @var Media $media */
        $media = $this->variant->getFirstMedia('variant_images');
        self::assertTrue((bool) $media->getCustomProperty('is_primary'));

        self::assertTrue($media->hasGeneratedConversion('thumb'));
        self::assertTrue($media->hasGeneratedConversion('small'));
        self::assertTrue($media->hasGeneratedConversion('medium'));
        self::assertTrue($media->hasGeneratedConversion('large'));
    }

    public function test_variant_primary_and_reorder_workflow(): void
    {
        $admin = $this->userWithRole('admin');

        $m1 = $this->variant->addMedia(UploadedFile::fake()->image('v1.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('variant_images');

        $m2 = $this->variant->addMedia(UploadedFile::fake()->image('v2.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => false])
            ->toMediaCollection('variant_images');

        // Set m2 as primary
        $this->actingAs($admin)
            ->patch("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media/{$m2->id}/primary")
            ->assertRedirect();

        self::assertFalse((bool) $m1->fresh()->getCustomProperty('is_primary'));
        self::assertTrue((bool) $m2->fresh()->getCustomProperty('is_primary'));

        // Reorder
        $this->actingAs($admin)
            ->post("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media/reorder", [
                'media_ids' => [$m2->id, $m1->id],
            ])
            ->assertRedirect();

        self::assertSame(1, $m2->fresh()->order_column);
        self::assertSame(2, $m1->fresh()->order_column);
    }

    public function test_delete_variant_primary_promotes_remaining(): void
    {
        $admin = $this->userWithRole('admin');

        $m1 = $this->variant->addMedia(UploadedFile::fake()->image('v1.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('variant_images');

        $m2 = $this->variant->addMedia(UploadedFile::fake()->image('v2.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => false])
            ->toMediaCollection('variant_images');

        $this->actingAs($admin)
            ->delete("/admin/products/{$this->product->id}/variants/{$this->variant->id}/media/{$m1->id}")
            ->assertRedirect();

        $this->variant->refresh();
        self::assertSame(1, $this->variant->getMedia('variant_images')->count());
        self::assertTrue((bool) $m2->fresh()->getCustomProperty('is_primary'));
    }

    public function test_idor_protection_variant_mismatch_returns_404(): void
    {
        $admin = $this->userWithRole('admin');

        $otherProduct = Product::query()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'name_ar' => 'منتج آخر',
            'slug' => 'other-serum',
            'status' => 'active',
            'has_variants' => true,
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 600, 600);

        // Attempting to upload to this->variant via otherProduct route
        $this->actingAs($admin)
            ->post("/admin/products/{$otherProduct->id}/variants/{$this->variant->id}/media", [
                'file' => $file,
            ])
            ->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => 'اختبار المستخدم',
            'email' => $role.'_'.uniqid().'@lamma.test',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $user->addRole(Role::query()->where('name', $role)->firstOrFail());

        return $user;
    }
}
