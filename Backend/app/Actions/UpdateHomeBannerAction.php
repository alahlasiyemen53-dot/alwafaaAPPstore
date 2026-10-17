<?php

namespace App\Actions;

use App\Data\UpdateHomeBannerData;
use App\Models\HomeBanner;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class UpdateHomeBannerAction
{
    private const AUDITED_FIELDS = [
        'title_ar', 'title_en', 'subtitle_ar', 'subtitle_en',
        'is_active', 'sort_order', 'starts_at', 'ends_at',
        'action_type', 'action_reference',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(HomeBanner $banner, UpdateHomeBannerData $data): HomeBanner
    {
        return DB::transaction(function () use ($banner, $data): HomeBanner {
            $before = Arr::only($banner->getAttributes(), self::AUDITED_FIELDS);

            $banner->update([
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

            if ($data->image !== null) {
                $banner->clearMediaCollection('banner_images');
                $banner->addMedia($data->image)->toMediaCollection('banner_images');
            }

            $after = Arr::only($banner->getAttributes(), self::AUDITED_FIELDS);

            $this->auditLogger->record('admin.home_banner.updated', $banner, metadata: [
                'before' => $before,
                'after' => $after,
                'media_replaced' => $data->image !== null,
            ]);

            Cache::forget('customer.home.legacy.v1');

            return $banner->fresh(['media']);
        });
    }
}
