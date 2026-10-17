<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminProductReviewWebTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Product $product;

    private ProductReview $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->customer = Customer::query()->create([
            'name' => 'بلال الأهدل',
            'phone' => '771122334',
            'normalized_phone' => '+967771122334',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create(['name_ar' => 'براند يمني', 'slug' => 'yemeni-brand', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'تصنيف العناية', 'slug' => 'care-cat', 'sort_order' => 1, 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'غسول الوجه المنعش',
            'slug' => 'refreshing-face-wash',
            'status' => ProductStatus::Active,
            'has_variants' => false,
        ]);

        $this->review = ProductReview::query()->create([
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'title' => 'جودة ممتازة',
            'comment' => 'منتج ممتاز جداً وأنصح به الجميع',
            'status' => ProductReviewStatus::Pending,
            'is_verified_purchase' => true,
        ]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/reviews')->assertRedirect('/admin/login');
        $this->get("/admin/reviews/{$this->review->id}")->assertRedirect('/admin/login');
        $this->post("/admin/reviews/{$this->review->id}/moderate", ['status' => 'approved'])->assertRedirect('/admin/login');
    }

    public function test_user_without_permission_cannot_view_or_moderate_reviews(): void
    {
        // Create user without reviews permissions
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/admin/reviews')->assertForbidden();
        $this->actingAs($user)->get("/admin/reviews/{$this->review->id}")->assertForbidden();
        $this->actingAs($user)->post("/admin/reviews/{$this->review->id}/moderate", ['status' => 'approved'])->assertForbidden();
    }

    public function test_authorized_user_can_view_reviews_index_and_details(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get('/admin/reviews');
        $response->assertOk()
            ->assertSee('تقييمات ومراجعات المنتجات')
            ->assertSee('غسول الوجه المنعش')
            ->assertSee('بلال الأهدل')
            ->assertSee('جودة ممتازة');

        $showResponse = $this->actingAs($admin)->get("/admin/reviews/{$this->review->id}");
        $showResponse->assertOk()
            ->assertSee("تفاصيل التقييم #{$this->review->id}")
            ->assertSee('شراء مؤكد')
            ->assertSee('منتج ممتاز جداً وأنصح به الجميع');
    }

    public function test_filter_and_search_reviews(): void
    {
        $admin = $this->userWithRole('admin');

        // Second review with distinct rating, status and text
        $c2 = Customer::query()->create([
            'name' => 'طارق الزبيري',
            'phone' => '779988776',
            'normalized_phone' => '+967779988776',
            'is_active' => true,
        ]);
        $r2 = ProductReview::query()->create([
            'customer_id' => $c2->id,
            'product_id' => $this->product->id,
            'rating' => 2,
            'title' => 'تجربة غير موفقة',
            'comment' => 'العلبة وصلت متضررة جزئياً',
            'status' => ProductReviewStatus::Rejected,
            'moderation_reason' => 'شكوى شحن وليست تقييم منتج',
            'is_verified_purchase' => false,
        ]);

        // Filter by status: pending
        $this->actingAs($admin)->get('/admin/reviews?status=pending')
            ->assertOk()
            ->assertSee('بلال الأهدل')
            ->assertDontSee('طارق الزبيري');

        // Filter by status: rejected
        $this->actingAs($admin)->get('/admin/reviews?status=rejected')
            ->assertOk()
            ->assertSee('طارق الزبيري')
            ->assertDontSee('بلال الأهدل');

        // Filter by rating: 5
        $this->actingAs($admin)->get('/admin/reviews?rating=5')
            ->assertOk()
            ->assertSee('بلال الأهدل')
            ->assertDontSee('طارق الزبيري');

        // Filter by verified purchase: 0
        $this->actingAs($admin)->get('/admin/reviews?verified=0')
            ->assertOk()
            ->assertSee('طارق الزبيري')
            ->assertDontSee('بلال الأهدل');

        // Search by customer name
        $this->actingAs($admin)->get('/admin/reviews?search=طارق')
            ->assertOk()
            ->assertSee('طارق الزبيري')
            ->assertDontSee('بلال الأهدل');
    }

    public function test_moderator_can_approve_review(): void
    {
        $moderator = $this->userWithRole('admin');

        $response = $this->actingAs($moderator)->post("/admin/reviews/{$this->review->id}/moderate", [
            'status' => 'approved',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $fresh = $this->review->fresh();
        self::assertSame(ProductReviewStatus::Approved, $fresh->status);
        self::assertSame($moderator->id, $fresh->moderator_id);
        self::assertNotNull($fresh->moderated_at);
        self::assertNull($fresh->moderation_reason);
    }

    public function test_reject_or_hide_requires_moderation_reason(): void
    {
        $moderator = $this->userWithRole('admin');

        // Reject without reason fails validation
        $response = $this->actingAs($moderator)->post("/admin/reviews/{$this->review->id}/moderate", [
            'status' => 'rejected',
            'reason' => '',
        ]);
        $response->assertSessionHasErrors(['reason']);

        // Hide without reason fails validation
        $response = $this->actingAs($moderator)->post("/admin/reviews/{$this->review->id}/moderate", [
            'status' => 'hidden',
            'reason' => '',
        ]);
        $response->assertSessionHasErrors(['reason']);
    }

    public function test_reject_with_reason_succeeds(): void
    {
        $moderator = $this->userWithRole('admin');

        $response = $this->actingAs($moderator)->post("/admin/reviews/{$this->review->id}/moderate", [
            'status' => 'rejected',
            'reason' => 'محتوى دعائي غير لائق يخالف شروط النشر',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $fresh = $this->review->fresh();
        self::assertSame(ProductReviewStatus::Rejected, $fresh->status);
        self::assertSame($moderator->id, $fresh->moderator_id);
        self::assertSame('محتوى دعائي غير لائق يخالف شروط النشر', $fresh->moderation_reason);
    }

    public function test_admin_cannot_fabricate_reviews_no_create_routes(): void
    {
        $admin = $this->userWithRole('admin');

        // GET /admin/reviews/create must not exist
        $this->actingAs($admin)->get('/admin/reviews/create')->assertNotFound();

        // POST /admin/reviews must not exist (returns 405 Method Not Allowed because only GET is registered)
        $this->actingAs($admin)->post('/admin/reviews', [
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
        ])->assertStatus(405);

        self::assertFalse(Route::has('admin.reviews.store'));
        self::assertFalse(Route::has('admin.reviews.create'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
