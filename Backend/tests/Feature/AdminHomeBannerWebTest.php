<?php

namespace Tests\Feature;

use App\Enums\BannerActionType;
use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminHomeBannerWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('public');
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/marketing/banners')->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $user = $this->userWithRole('customer_support');
        $this->actingAs($user)->get('/admin/marketing/banners')->assertForbidden();
    }

    public function test_admin_can_create_and_view_banner(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $category = Category::query()->create(['name_ar' => 'تصنيف الجمال', 'slug' => 'beauty', 'is_active' => true]);

        $image = UploadedFile::fake()->image('test_banner.jpg', 1200, 600);

        $response = $this->post('/admin/marketing/banners', [
            'title_ar' => 'بنر منتجات الجمال',
            'title_en' => 'Beauty Products Banner',
            'subtitle_ar' => 'خصومات حصرية',
            'is_active' => '1',
            'sort_order' => 2,
            'action_type' => BannerActionType::Category->value,
            'action_reference' => (string) $category->id,
            'image' => $image,
        ]);

        $response->assertRedirect('/admin/marketing/banners');

        $banner = HomeBanner::query()->where('title_ar', 'بنر منتجات الجمال')->firstOrFail();
        self::assertSame(BannerActionType::Category, $banner->action_type);

        $indexRes = $this->get('/admin/marketing/banners');
        $indexRes->assertOk();
        $indexRes->assertSee('بنر منتجات الجمال');
        $indexRes->assertSee('تصنيف الجمال');

        $showRes = $this->get("/admin/marketing/banners/{$banner->id}");
        $showRes->assertOk();
        $showRes->assertSee('بنر منتجات الجمال');
    }

    public function test_invalid_schedule_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $image = UploadedFile::fake()->image('test_banner.jpg', 1200, 600);

        $response = $this->post('/admin/marketing/banners', [
            'title_ar' => 'بنر غير صالح',
            'starts_at' => '2026-08-10 10:00:00',
            'ends_at' => '2026-08-01 10:00:00', // ends_at before starts_at
            'action_type' => BannerActionType::None->value,
            'image' => $image,
        ]);

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_banner_title_is_escaped_against_xss(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $banner = HomeBanner::query()->create([
            'title_ar' => '<script>alert("xss")</script>',
            'subtitle_ar' => '<b>safe</b>',
            'is_active' => true,
        ]);

        $res = $this->get('/admin/marketing/banners');
        $res->assertOk();
        $res->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
        $res->assertDontSee('<script>alert("xss")</script>', false);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            ['display_name' => ucfirst($role), 'description' => ucfirst($role)]
        );
        $user->addRole($roleModel);

        return $user;
    }
}
