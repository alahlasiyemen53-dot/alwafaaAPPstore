<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\ReviewCommentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReviewComment;
use App\Models\Role;
use App\Models\User;
use App\Queries\GetProductRatingSummaryQuery;
use App\Services\Auth\CustomerJwtService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerReviewCommentsApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Product $product;

    private ProductReview $approvedReview;

    private Customer $reviewOwner;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->seed(AuthorizationSeeder::class);
        $this->jwtService = app(CustomerJwtService::class);

        $brand = Brand::query()->create(['name_ar' => 'براند', 'slug' => 'brand-rc', 'is_active' => true]);
        $category = Category::query()->create(['name_ar' => 'تصنيف', 'slug' => 'cat-rc', 'sort_order' => 1, 'is_active' => true]);

        $this->product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'عطر الاختبار',
            'slug' => 'perfume-rc',
            'status' => ProductStatus::Active,
            'has_variants' => false,
        ]);

        $this->reviewOwner = $this->createCustomer('أحمد محمد', '771111111');

        $this->approvedReview = ProductReview::query()->create([
            'customer_id' => $this->reviewOwner->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'title' => 'رائع',
            'body' => 'العطر ممتاز وثباته جميل.',
            'status' => ProductReviewStatus::Approved,
            'is_verified_purchase' => true,
        ]);
    }

    public function test_guest_can_list_public_comments(): void
    {
        ReviewComment::query()->create([
            'review_id' => $this->approvedReview->id,
            'customer_id' => $this->createCustomer('سارة علي', '772222222')->id,
            'admin_user_id' => null,
            'body' => 'هل الثبات يستمر طوال اليوم؟',
            'status' => ReviewCommentStatus::Approved,
        ]);

        $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.body', 'هل الثبات يستمر طوال اليوم؟')
            ->assertJsonPath('data.items.0.author.display_name', 'سارة ع.')
            ->assertJsonMissingPath('data.items.0.author.phone')
            ->assertJsonMissingPath('data.items.0.customer');
    }

    public function test_guest_cannot_create_comment(): void
    {
        $this->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
            'body' => 'تعليق بدون تسجيل',
        ])
            ->assertStatus(401)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_authenticated_customer_can_comment_on_approved_review(): void
    {
        $customer = $this->createCustomer('خالد حسن', '773333333');
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
                'body' => '  سؤال عن الثبات  ',
                'admin_user_id' => 1,
                'is_admin' => true,
                'author_type' => 'admin',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_official_admin_reply', false)
            ->assertJsonPath('data.author.type', 'customer')
            ->assertJsonPath('data.body', 'سؤال عن الثبات');

        $this->assertDatabaseHas('review_comments', [
            'review_id' => $this->approvedReview->id,
            'customer_id' => $customer->id,
            'admin_user_id' => null,
            'body' => 'سؤال عن الثبات',
        ]);
    }

    public function test_customer_cannot_comment_on_hidden_review(): void
    {
        $hidden = ProductReview::query()->create([
            'customer_id' => $this->createCustomer('عمر ناصر', '774444444')->id,
            'product_id' => $this->product->id,
            'rating' => 2,
            'body' => 'مخفي',
            'status' => ProductReviewStatus::Hidden,
            'is_verified_purchase' => true,
        ]);

        // Unique constraint is per customer+product — use different product for second review
        // Actually unique is customer_id+product_id, so we need another product OR same product different customer - done.
        // Wait - we used different customer, same product - OK.

        $token = $this->jwtService->generateAccessToken($this->createCustomer('ليلى', '775555555'));

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$hidden->id}/comments", ['body' => 'تعليق'])
            ->assertNotFound()
            ->assertJsonPath('data.code', 'REVIEW_NOT_FOUND');

        $this->getJson("/api/v1/reviews/{$hidden->id}/comments")
            ->assertNotFound()
            ->assertJsonPath('data.code', 'REVIEW_NOT_FOUND');
    }

    public function test_comment_body_required_and_bounded_and_html_stripped(): void
    {
        $token = $this->jwtService->generateAccessToken($this->createCustomer('نور', '776666666'));

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonPath('data.code', 'VALIDATION_FAILED');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
                'body' => str_repeat('أ', 1001),
            ])
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
                'body' => '<script>alert(1)</script>نص آمن',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'alert(1)نص آمن');
    }

    public function test_hidden_comment_not_public(): void
    {
        $visible = ReviewComment::query()->create([
            'review_id' => $this->approvedReview->id,
            'customer_id' => $this->createCustomer('ظاهر', '777777777')->id,
            'body' => 'تعليق ظاهر',
            'status' => ReviewCommentStatus::Approved,
        ]);

        ReviewComment::query()->create([
            'review_id' => $this->approvedReview->id,
            'customer_id' => $this->createCustomer('مخفي', '778888888')->id,
            'body' => 'تعليق مخفي',
            'status' => ReviewCommentStatus::Hidden,
        ]);

        $response = $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments");
        $response->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->assertSame($visible->id, $response->json('data.items.0.id'));
    }

    public function test_product_reviews_include_bounded_preview_and_do_not_alter_rating(): void
    {
        $before = app(GetProductRatingSummaryQuery::class)->execute($this->product);

        $commenter = $this->createCustomer('معلق', '779999991');
        for ($i = 1; $i <= 4; $i++) {
            ReviewComment::query()->create([
                'review_id' => $this->approvedReview->id,
                'customer_id' => $commenter->id,
                'body' => "تعليق رقم {$i}",
                'status' => ReviewCommentStatus::Approved,
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        // Need unique customers for XOR author - actually same customer can have multiple comments
        // Re-create with unique if needed - same customer_id is fine for multiple comments.

        $response = $this->getJson("/api/v1/products/{$this->product->id}/reviews");
        $response->assertOk()
            ->assertJsonPath('data.items.0.comment_count', 4)
            ->assertJsonPath('data.items.0.is_verified_purchase', true);

        $preview = $response->json('data.items.0.comments_preview');
        $this->assertCount(2, $preview);
        $this->assertSame('تعليق رقم 1', $preview[0]['body']);
        $this->assertSame('تعليق رقم 2', $preview[1]['body']);

        $after = app(GetProductRatingSummaryQuery::class)->execute($this->product);
        $this->assertSame($before['average_rating'], $after['average_rating']);
        $this->assertSame($before['reviews_count'], $after['reviews_count']);
        $this->assertSame($before['distribution'], $after['distribution']);
    }

    public function test_comments_pagination_is_deterministic(): void
    {
        $c = $this->createCustomer('صفحة', '779999992');
        for ($i = 1; $i <= 3; $i++) {
            ReviewComment::query()->create([
                'review_id' => $this->approvedReview->id,
                'customer_id' => $c->id,
                'body' => "C{$i}",
                'status' => ReviewCommentStatus::Approved,
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }

        $page1 = $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments?per_page=2&page=1")
            ->assertOk();
        $this->assertSame(['C1', 'C2'], collect($page1->json('data.items'))->pluck('body')->all());
        $this->assertTrue($page1->json('data.pagination.has_more'));

        $page2 = $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments?per_page=2&page=2")
            ->assertOk();
        $this->assertSame(['C3'], collect($page2->json('data.items'))->pluck('body')->all());
    }

    public function test_authorized_admin_can_reply_and_unauthorized_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $unauthorized = User::factory()->create(['is_active' => true]);

        $this->actingAs($unauthorized)
            ->post("/admin/reviews/{$this->approvedReview->id}/comments", ['body' => 'رد'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post("/admin/reviews/{$this->approvedReview->id}/comments", ['body' => 'يختلف حسب الاستخدام'])
            ->assertRedirect();

        $this->assertDatabaseHas('review_comments', [
            'review_id' => $this->approvedReview->id,
            'admin_user_id' => $admin->id,
            'customer_id' => null,
            'body' => 'يختلف حسب الاستخدام',
        ]);

        $list = $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments")->assertOk();
        $adminItem = collect($list->json('data.items'))->firstWhere('is_official_admin_reply', true);
        $this->assertNotNull($adminItem);
        $this->assertSame('إدارة لمّة', $adminItem['author']['display_name']);
    }

    public function test_admin_can_hide_and_restore_comment(): void
    {
        $admin = $this->userWithRole('admin');
        $comment = ReviewComment::query()->create([
            'review_id' => $this->approvedReview->id,
            'customer_id' => $this->createCustomer('إخفاء', '779999993')->id,
            'body' => 'سيُخفى',
            'status' => ReviewCommentStatus::Approved,
        ]);

        $this->actingAs($admin)
            ->post("/admin/reviews/{$this->approvedReview->id}/comments/{$comment->id}/moderate", [
                'action' => 'hide',
            ])
            ->assertRedirect();

        $this->assertSame(ReviewCommentStatus::Hidden, $comment->fresh()->status);

        $this->getJson("/api/v1/reviews/{$this->approvedReview->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->actingAs($admin)
            ->post("/admin/reviews/{$this->approvedReview->id}/comments/{$comment->id}/moderate", [
                'action' => 'restore',
            ])
            ->assertRedirect();

        $this->assertSame(ReviewCommentStatus::Approved, $comment->fresh()->status);
    }

    public function test_author_xor_is_enforced_at_application_layer(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReviewComment::query()->create([
            'review_id' => $this->approvedReview->id,
            'customer_id' => null,
            'admin_user_id' => null,
            'body' => 'invalid',
            'status' => ReviewCommentStatus::Approved,
        ]);
    }

    public function test_comment_create_is_rate_limited(): void
    {
        $token = $this->jwtService->generateAccessToken($this->createCustomer('معدل', '779999994'));

        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
                    'body' => "تعليق {$i}",
                ])
                ->assertCreated();
        }

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/reviews/{$this->approvedReview->id}/comments", [
                'body' => 'زيادة',
            ])
            ->assertStatus(429)
            ->assertJsonPath('data.code', 'RATE_LIMITED');
    }

    private function createCustomer(string $name, string $phone): Customer
    {
        return Customer::query()->create([
            'name' => $name,
            'phone' => $phone,
            'normalized_phone' => '+967'.$phone,
            'is_active' => true,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->addRole($role);

        return $user;
    }
}
