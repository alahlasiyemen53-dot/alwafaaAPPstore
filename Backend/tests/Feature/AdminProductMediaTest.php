<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class AdminProductMediaTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

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
            'name_ar' => 'كريم اليدين',
            'name_en' => 'Hand Cream',
            'slug' => 'hand-cream',
            'status' => 'active',
            'has_variants' => false,
        ]);
    }

    public function test_guest_cannot_upload_product_media(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 600, 600);
        $this->post("/admin/products/{$this->product->id}/media", ['file' => $file])
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_upload_product_media(): void
    {
        $user = $this->userWithRole('customer_support');
        $file = UploadedFile::fake()->image('test.jpg', 600, 600);

        $this->actingAs($user)
            ->post("/admin/products/{$this->product->id}/media", ['file' => $file])
            ->assertForbidden();
    }

    public function test_authorized_admin_uploads_product_image_and_generates_webp_conversions(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->image('photo.jpg', 1600, 1600);

        $response = $this->actingAs($admin)
            ->post("/admin/products/{$this->product->id}/media", [
                'file' => $file,
                'name' => 'صورة العبوة',
            ]);

        $response->assertRedirect();
        $this->product->refresh();

        self::assertSame(1, $this->product->getMedia('product_images')->count());

        /** @var Media $media */
        $media = $this->product->getFirstMedia('product_images');
        self::assertSame('صورة العبوة', $media->name);
        self::assertTrue((bool) $media->getCustomProperty('is_primary'));

        // Check WebP conversions were registered
        self::assertTrue($media->hasGeneratedConversion('thumb'));
        self::assertTrue($media->hasGeneratedConversion('small'));
        self::assertTrue($media->hasGeneratedConversion('medium'));
        self::assertTrue($media->hasGeneratedConversion('large'));
    }

    public function test_second_image_upload_without_primary_flag_remains_secondary(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post("/admin/products/{$this->product->id}/media", [
            'file' => UploadedFile::fake()->image('first.jpg', 800, 800),
        ]);

        $this->actingAs($admin)->post("/admin/products/{$this->product->id}/media", [
            'file' => UploadedFile::fake()->image('second.jpg', 800, 800),
            'is_primary' => 0,
        ]);

        $this->product->refresh();
        $images = $this->product->getMedia('product_images');

        self::assertCount(2, $images);
        self::assertTrue((bool) $images[0]->getCustomProperty('is_primary'));
        self::assertFalse((bool) $images[1]->getCustomProperty('is_primary'));
    }

    public function test_uploading_with_is_primary_flag_demotes_existing_primary(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post("/admin/products/{$this->product->id}/media", [
            'file' => UploadedFile::fake()->image('first.jpg', 800, 800),
        ]);

        $this->actingAs($admin)->post("/admin/products/{$this->product->id}/media", [
            'file' => UploadedFile::fake()->image('second.jpg', 800, 800),
            'is_primary' => 1,
        ]);

        $this->product->refresh();
        $images = $this->product->getMedia('product_images');

        self::assertFalse((bool) $images[0]->getCustomProperty('is_primary'));
        self::assertTrue((bool) $images[1]->getCustomProperty('is_primary'));
    }

    public function test_set_primary_media_switches_primary_image(): void
    {
        $admin = $this->userWithRole('admin');

        $m1 = $this->product->addMedia(UploadedFile::fake()->image('img1.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('product_images');

        $m2 = $this->product->addMedia(UploadedFile::fake()->image('img2.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => false])
            ->toMediaCollection('product_images');

        $this->actingAs($admin)
            ->patch("/admin/products/{$this->product->id}/media/{$m2->id}/primary")
            ->assertRedirect();

        $this->product->refresh();
        self::assertFalse((bool) $m1->fresh()->getCustomProperty('is_primary'));
        self::assertTrue((bool) $m2->fresh()->getCustomProperty('is_primary'));
    }

    public function test_reorder_product_media(): void
    {
        $admin = $this->userWithRole('admin');

        $m1 = $this->product->addMedia(UploadedFile::fake()->image('img1.jpg', 600, 600))
            ->toMediaCollection('product_images');
        $m2 = $this->product->addMedia(UploadedFile::fake()->image('img2.jpg', 600, 600))
            ->toMediaCollection('product_images');

        $this->actingAs($admin)
            ->post("/admin/products/{$this->product->id}/media/reorder", [
                'media_ids' => [$m2->id, $m1->id],
            ])
            ->assertRedirect();

        self::assertSame(1, $m2->fresh()->order_column);
        self::assertSame(2, $m1->fresh()->order_column);
    }

    public function test_delete_primary_image_promotes_next_remaining_image(): void
    {
        $admin = $this->userWithRole('admin');

        $m1 = $this->product->addMedia(UploadedFile::fake()->image('img1.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('product_images');

        $m2 = $this->product->addMedia(UploadedFile::fake()->image('img2.jpg', 600, 600))
            ->withCustomProperties(['is_primary' => false])
            ->toMediaCollection('product_images');

        $this->actingAs($admin)
            ->delete("/admin/products/{$this->product->id}/media/{$m1->id}")
            ->assertRedirect();

        $this->product->refresh();
        self::assertSame(1, $this->product->getMedia('product_images')->count());
        self::assertTrue((bool) $m2->fresh()->getCustomProperty('is_primary'));
    }

    public function test_idor_protection_cannot_delete_or_primary_media_of_other_product(): void
    {
        $admin = $this->userWithRole('admin');

        $otherProduct = Product::query()->create([
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'name_ar' => 'منتج آخر',
            'slug' => 'other-product',
            'status' => 'active',
            'has_variants' => false,
        ]);

        $otherMedia = $otherProduct->addMedia(UploadedFile::fake()->image('other.jpg', 600, 600))
            ->toMediaCollection('product_images');

        // Trying to delete otherProduct's media via this product's route
        $this->actingAs($admin)
            ->delete("/admin/products/{$this->product->id}/media/{$otherMedia->id}")
            ->assertNotFound();

        // Trying to set primary otherProduct's media via this product's route
        $this->actingAs($admin)
            ->patch("/admin/products/{$this->product->id}/media/{$otherMedia->id}/primary")
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
