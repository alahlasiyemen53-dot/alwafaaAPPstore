<?php

namespace App\Actions;

use App\Data\StoreHomeBannerData;
use App\Models\HomeBanner;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CreateHomeBannerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StoreHomeBannerData $data): HomeBanner
    {
        return DB::transaction(function () use ($data): HomeBanner {
            $banner = HomeBanner::query()->create([
                'title_ar' => $data->titleAr,
                'title_en' => $data->titleEn,
                'subtitle_ar' => $data->subtitleAr,
                'subtitle_en' => $data->subtitleEn,
                'is_active' => $data->isActive,
                'sort_order' => $data->sortOrder,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'action_type' => $data->actionType,
                'action_reference' => $data->actionReference,
            ]);

            $banner->addMedia($data->image)
                ->toMediaCollection('banner_images');

            $this->auditLogger->record('admin.home_banner.created', $banner, metadata: [
                'title_ar' => $banner->title_ar,
                'action_type' => $banner->action_type->value,
                'action_reference' => $banner->action_reference,
            ]);

            Cache::forget('customer.home.legacy.v1');

            return $banner->fresh(['media']);
        });
    }
}
