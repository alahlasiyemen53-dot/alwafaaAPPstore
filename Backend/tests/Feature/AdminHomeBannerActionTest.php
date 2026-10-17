<?php

namespace Tests\Feature;

use App\Actions\CreateHomeBannerAction;
use App\Actions\DeleteHomeBannerAction;
use App\Actions\ToggleHomeBannerStatusAction;
use App\Actions\UpdateHomeBannerAction;
use App\Data\StoreHomeBannerData;
use App\Data\UpdateHomeBannerData;
use App\Enums\BannerActionType;
use App\Models\AuditLog;
use App\Models\HomeBanner;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminHomeBannerActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('public');
    }

    public function test_create_and_update_banner_actions_with_media_and_audit(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $image = UploadedFile::fake()->image('banner1.jpg', 1200, 600);

        $storeData = new StoreHomeBannerData(
            titleAr: 'بنر العيد',
            titleEn: 'Eid Banner',
            subtitleAr: 'أقوى العروض',
            subtitleEn: 'Best Offers',
            isActive: true,
            sortOrder: 1,
            startsAt: null,
            endsAt: null,
            actionType: BannerActionType::None,
            actionReference: null,
            image: $image,
        );

        $createAction = app(CreateHomeBannerAction::class);
        $banner = $createAction->execute($storeData);

        self::assertNotNull($banner->getFirstMedia('banner_images'));
        self::assertSame('بنر العيد', $banner->title_ar);

        $createLog = AuditLog::query()->where('action', 'admin.home_banner.created')->first();
        self::assertNotNull($createLog);

        // Test Update
        $newImage = UploadedFile::fake()->image('banner_updated.webp', 1200, 600);
        $updateData = new UpdateHomeBannerData(
            titleAr: 'بنر العيد المحدث',
            titleEn: 'Updated Eid Banner',
            subtitleAr: 'أقوى العروض الحصرية',
            subtitleEn: 'Exclusive Best Offers',
            isActive: true,
            sortOrder: 5,
            startsAt: null,
            endsAt: null,
            actionType: BannerActionType::None,
            actionReference: null,
            image: $newImage,
        );

        $updateAction = app(UpdateHomeBannerAction::class);
        $updated = $updateAction->execute($banner, $updateData);

        self::assertSame('بنر العيد المحدث', $updated->title_ar);
        self::assertSame(5, $updated->sort_order);

        $updateLog = AuditLog::query()->where('action', 'admin.home_banner.updated')->first();
        self::assertNotNull($updateLog);
        self::assertTrue($updateLog->metadata['media_replaced']);

        // Test Toggle
        $toggleAction = app(ToggleHomeBannerStatusAction::class);
        $toggled = $toggleAction->execute($updated);
        self::assertFalse($toggled->is_active);

        // Test Delete
        $deleteAction = app(DeleteHomeBannerAction::class);
        $deleteAction->execute($toggled);
        self::assertDatabaseMissing('home_banners', ['id' => $banner->id]);
    }
}
