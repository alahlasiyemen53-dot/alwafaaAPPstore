<?php

namespace App\Actions;

use App\Models\HomeBanner;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class ToggleHomeBannerStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(HomeBanner $banner): HomeBanner
    {
        return DB::transaction(function () use ($banner): HomeBanner {
            $previousStatus = $banner->is_active;
            $newStatus = ! $previousStatus;

            $banner->update(['is_active' => $newStatus]);

            $this->auditLogger->record('admin.home_banner.status_toggled', $banner, metadata: [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ]);

            return $banner->fresh();
        });
    }
}
