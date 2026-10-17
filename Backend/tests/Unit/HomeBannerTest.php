<?php

namespace Tests\Unit;

use App\Enums\BannerActionType;
use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\Carbon;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_banner_defaults_and_casts(): void
    {
        $banner = HomeBanner::query()->create([
            'title_ar' => 'بنر افتتاحي',
        ]);

        self::assertTrue($banner->is_active);
        self::assertSame(0, $banner->sort_order);
        self::assertSame(BannerActionType::None, $banner->action_type);
        self::assertNull($banner->starts_at);
        self::assertNull($banner->ends_at);
    }

    public function test_banner_scopes(): void
    {
        $now = Carbon::parse('2026-06-15 12:00:00');

        $activeScheduled = HomeBanner::query()->create([
            'title_ar' => 'نشط ومجدول',
            'is_active' => true,
            'sort_order' => 1,
            'starts_at' => Carbon::parse('2026-06-01'),
            'ends_at' => Carbon::parse('2026-06-30'),
        ]);

        $futureBanner = HomeBanner::query()->create([
            'title_ar' => 'مستقبلي',
            'is_active' => true,
            'sort_order' => 2,
            'starts_at' => Carbon::parse('2026-07-01'),
            'ends_at' => Carbon::parse('2026-07-31'),
        ]);

        $inactiveBanner = HomeBanner::query()->create([
            'title_ar' => 'معطل',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        self::assertCount(2, HomeBanner::query()->active()->get());
        self::assertSame([$activeScheduled->id], HomeBanner::query()->currentlyScheduled($now)->pluck('id')->all());
        self::assertSame([$inactiveBanner->id, $activeScheduled->id, $futureBanner->id], HomeBanner::query()->ordered()->pluck('id')->all());
    }

    public function test_banner_destination_resolution(): void
    {
        $category = Category::query()->create([
            'name_ar' => 'العناية بالبشرة',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $banner = HomeBanner::query()->create([
            'title_ar' => 'عروض العناية',
            'action_type' => BannerActionType::Category,
            'action_reference' => (string) $category->id,
        ]);

        $entity = $banner->getDestinationEntity();
        self::assertNotNull($entity);
        self::assertInstanceOf(Category::class, $entity);
        self::assertSame('العناية بالبشرة', $entity->name_ar);
        self::assertStringContainsString('العناية بالبشرة', $banner->getDestinationLabel());
    }
}
