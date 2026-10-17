<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use Illuminate\Console\Command;

final class ReplayPendingNotificationsCommand extends Command
{
    protected $signature = 'notifications:replay-pending {--limit=100}';

    protected $description = 'Requeue stale notification IDs with pending delivery work.';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 500);
        $notificationIds = BusinessNotification::query()
            ->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Queued])
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->limit($limit)
            ->pluck('id');

        foreach ($notificationIds as $notificationId) {
            SendPushNotificationJob::dispatch($notificationId)->onQueue('notifications');
        }

        $this->info("Requeued {$notificationIds->count()} notification jobs.");

        return self::SUCCESS;
    }
}
