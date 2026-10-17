<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\AppSemver;
use Illuminate\Support\Facades\Cache;

final class AppReleaseService
{
    private const CACHE_KEY = 'settings.app_release';

    /**
     * @return array{
     *     latest_version: ?string,
     *     min_version: ?string,
     *     is_force: bool,
     *     android_store_url: ?string,
     *     ios_store_url: ?string
     * }
     */
    public function getReleaseInfo(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            return $this->buildReleaseInfo();
        });
    }

    /**
     * Record a running client version. If it is newer than the known latest,
     * persist it so other devices receive the update gate automatically.
     */
    public function recordClientVersion(?string $clientVersion): void
    {
        if (! AppSemver::isValid($clientVersion)) {
            return;
        }

        $clientVersion = trim((string) $clientVersion);
        $current = $this->resolveLatestVersion();

        if ($current !== null && AppSemver::compare($clientVersion, $current) <= 0) {
            return;
        }

        Setting::updateOrCreate(
            ['key' => 'app_latest_version'],
            ['value' => $clientVersion, 'type' => 'string']
        );
        $this->clearCache();
    }

    /** Sync DB setting from monorepo pubspec (deploy / artisan). */
    public function syncFromPubspec(): ?string
    {
        $fromPubspec = $this->readPubspecVersion();
        if ($fromPubspec === null) {
            return null;
        }

        $this->recordClientVersion($fromPubspec);

        return $this->resolveLatestVersion();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     latest_version: ?string,
     *     min_version: ?string,
     *     is_force: bool,
     *     android_store_url: ?string,
     *     ios_store_url: ?string
     * }
     */
    private function buildReleaseInfo(): array
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'app_latest_version',
                'app_min_version',
                'app_force_update',
                'app_android_store_url',
                'app_ios_store_url',
            ])
            ->pluck('value', 'key');

        $android = $this->nullableString(
            $settings['app_android_store_url'] ?? config('app_release.android_store_url')
        );
        $ios = $this->nullableString(
            $settings['app_ios_store_url'] ?? config('app_release.ios_store_url')
        );
        $minVersion = $this->nullableString(
            $settings['app_min_version'] ?? config('app_release.min_version')
        );
        $forceUpdate = filter_var(
            $settings['app_force_update'] ?? config('app_release.force_update', false),
            FILTER_VALIDATE_BOOLEAN
        );

        return [
            'latest_version' => $this->resolveLatestVersionFromSources($settings),
            'min_version' => $minVersion,
            'is_force' => $forceUpdate,
            'android_store_url' => $android,
            'ios_store_url' => $ios,
        ];
    }

    private function resolveLatestVersion(): ?string
    {
        $settings = Setting::query()
            ->whereIn('key', ['app_latest_version'])
            ->pluck('value', 'key');

        return $this->resolveLatestVersionFromSources($settings);
    }

    /**
     * @param  array<string, mixed>|\Illuminate\Support\Collection<string, mixed>  $settings
     */
    private function resolveLatestVersionFromSources(array|\Illuminate\Support\Collection $settings): ?string
    {
        $stored = $this->nullableString($settings['app_latest_version'] ?? null);

        return AppSemver::max(
            $stored,
            $this->nullableString(config('app_release.latest_version')),
            $this->readPubspecVersion(),
        );
    }

    public function readPubspecVersion(): ?string
    {
        $path = (string) config('app_release.pubspec_path', '');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/^version:\s*([^\s#]+)/m', $contents, $matches)) {
            return null;
        }

        $version = trim($matches[1], " \t\"'");

        return AppSemver::isValid($version) ? $version : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
