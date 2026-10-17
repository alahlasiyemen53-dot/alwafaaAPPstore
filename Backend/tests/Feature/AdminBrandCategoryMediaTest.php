<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminBrandCategoryMediaTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(AuthorizationSeeder::class);

        $this->brand = Brand::query()->create(['name_ar' => 'براند لمّة', 'slug' => 'brand-lamma', 'is_active' => true, 'sort_order' => 0]);
        $this->category = Category::query()->create(['name_ar' => 'العناية', 'slug' => 'care', 'is_active' => true]);
    }

    public function test_guest_cannot_upload_brand_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 300, 300);
        $this->post("/admin/master-data/brands/{$this->brand->id}/logo", ['file' => $file])
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_upload_brand_logo(): void
    {
        $user = $this->userWithRole('customer_support');
        $file = UploadedFile::fake()->image('logo.jpg', 300, 300);

        $this->actingAs($user)
            ->post("/admin/master-data/brands/{$this->brand->id}/logo", ['file' => $file])
            ->assertForbidden();
    }

    public function test_authorized_admin_uploads_brand_logo_and_generates_conversions(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->image('logo.jpg', 600, 600);

        $this->actingAs($admin)
            ->post("/admin/master-data/brands/{$this->brand->id}/logo", ['file' => $file])
            ->assertRedirect();

        $this->brand->refresh();
        self::assertSame(1, $this->brand->getMedia('brand_logo')->count());

        $media = $this->brand->getFirstMedia('brand_logo');
        self::assertTrue($media->hasGeneratedConversion('thumb'));
        self::assertTrue($media->hasGeneratedConversion('small'));
    }

    public function test_uploading_new_brand_logo_replaces_previous_single_file(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post("/admin/master-data/brands/{$this->brand->id}/logo", [
            'file' => UploadedFile::fake()->image('first.jpg', 400, 400),
        ]);
        $this->actingAs($admin)->post("/admin/master-data/brands/{$this->brand->id}/logo", [
            'file' => UploadedFile::fake()->image('second.jpg', 400, 400),
        ]);

        $this->brand->refresh();
        self::assertSame(1, $this->brand->getMedia('brand_logo')->count());
    }

    public function test_invalid_mime_type_rejected_for_brand_logo(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->create('malware.php', 10, 'application/x-php');

        $this->actingAs($admin)
            ->post("/admin/master-data/brands/{$this->brand->id}/logo", ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_oversized_brand_logo_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->image('big.jpg')->size(3000);

        $this->actingAs($admin)
            ->post("/admin/master-data/brands/{$this->brand->id}/logo", ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_authorized_admin_deletes_brand_logo(): void
    {
        $admin = $this->userWithRole('admin');
        $this->brand->addMedia(UploadedFile::fake()->image('logo.jpg', 400, 400))->toMediaCollection('brand_logo');

        $this->actingAs($admin)
            ->delete("/admin/master-data/brands/{$this->brand->id}/logo")
            ->assertRedirect();

        $this->brand->refresh();
        self::assertSame(0, $this->brand->getMedia('brand_logo')->count());
    }

    public function test_deleting_brand_logo_does_not_delete_brand_record(): void
    {
        $admin = $this->userWithRole('admin');
        $this->brand->addMedia(UploadedFile::fake()->image('logo.jpg', 400, 400))->toMediaCollection('brand_logo');

        $this->actingAs($admin)->delete("/admin/master-data/brands/{$this->brand->id}/logo");

        self::assertNotNull($this->brand->fresh());
    }

    public function test_authorized_admin_uploads_category_image(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->image('cat.jpg', 600, 600);

        $this->actingAs($admin)
            ->post("/admin/master-data/categories/{$this->category->id}/image", ['file' => $file])
            ->assertRedirect();

        $this->category->refresh();
        self::assertSame(1, $this->category->getMedia('category_image')->count());
    }

    public function test_category_without_image_remains_valid(): void
    {
        self::assertNull($this->category->getFirstMedia('category_image'));
        self::assertTrue($this->category->is_active);
    }

    public function test_authorized_admin_deletes_category_image(): void
    {
        $admin = $this->userWithRole('admin');
        $this->category->addMedia(UploadedFile::fake()->image('cat.jpg', 400, 400))->toMediaCollection('category_image');

        $this->actingAs($admin)
            ->delete("/admin/master-data/categories/{$this->category->id}/image")
            ->assertRedirect();

        $this->category->refresh();
        self::assertSame(0, $this->category->getMedia('category_image')->count());
    }

    public function test_user_without_permission_cannot_upload_category_image(): void
    {
        $user = $this->userWithRole('customer_support');
        $file = UploadedFile::fake()->image('cat.jpg', 300, 300);

        $this->actingAs($user)
            ->post("/admin/master-data/categories/{$this->category->id}/image", ['file' => $file])
            ->assertForbidden();
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
