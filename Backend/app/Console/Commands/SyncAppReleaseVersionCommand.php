<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AppReleaseService;
use Illuminate\Console\Command;

final class SyncAppReleaseVersionCommand extends Command
{
    protected $signature = 'app:sync-release-version';

    protected $description = 'Sync app_latest_version from apk/pubspec.yaml (run after bumping the Flutter version)';

    public function handle(AppReleaseService $appReleaseService): int
    {
        $fromPubspec = $appReleaseService->readPubspecVersion();
        if ($fromPubspec === null) {
            $this->error('Could not read a valid version from pubspec.yaml.');
            $this->line('Path: '.config('app_release.pubspec_path'));

            return self::FAILURE;
        }

        $latest = $appReleaseService->syncFromPubspec();
        $this->info("Latest app version is now: {$latest} (from pubspec {$fromPubspec})");

        return self::SUCCESS;
    }
}
