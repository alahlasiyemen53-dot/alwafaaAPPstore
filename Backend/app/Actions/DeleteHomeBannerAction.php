<?php

namespace App\Actions;

use App\Models\HomeBanner;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DeleteHomeBannerAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(HomeBanner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            $metadata = [
                'title_ar' => $banner->title_ar,
                'action_type' => $banner->action_type->value,
                'action_reference' => $banner->action_reference,
            ];

            $banner->clearMediaCollection('banner_images');
            $banner->delete();

            $this->auditLogger->record('admin.home_banner.deleted', null, metadata: $metadata);

            Cache::forget('customer.home.legacy.v1');
        });
    }
}
