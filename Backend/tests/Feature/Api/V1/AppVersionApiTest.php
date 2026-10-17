<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use App\Services\AppReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class AppVersionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $tempPubspec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPubspec = storage_path('framework/testing-pubspec-'.uniqid('', true).'.yaml');
        config(['app_release.pubspec_path' => $this->tempPubspec]);
        config(['app_release.latest_version' => '']);
        app(AppReleaseService::class)->clearCache();
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempPubspec)) {
            File::delete($this->tempPubspec);
        }
        parent::tearDown();
    }

    public function test_app_version_endpoint_returns_settings_values(): void
    {
        Setting::updateOrCreate(
            ['key' => 'app_latest_version'],
            ['value' => '1.1.0+1', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'app_android_store_url'],
            ['value' => 'https://play.google.com/store/apps/details?id=com.alwafaa.lamma_app', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'app_ios_store_url'],
            ['value' => 'https://apps.apple.com/app/id123456789', 'type' => 'string']
        );

        Setting::updateOrCreate(
            ['key' => 'app_min_version'],
            ['value' => '1.0.5', 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'app_force_update'],
            ['value' => '1', 'type' => 'boolean']
        );

        app(AppReleaseService::class)->clearCache();

        $this->getJson('/api/v1/app/version')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latest_version', '1.1.0+1')
            ->assertJsonPath('data.min_version', '1.0.5')
            ->assertJsonPath('data.is_force', true)
            ->assertJsonPath(
                'data.android_store_url',
                'https://play.google.com/store/apps/details?id=com.alwafaa.lamma_app'
            )
            ->assertJsonPath('data.ios_store_url', 'https://apps.apple.com/app/id123456789');
    }

    public function test_app_version_endpoint_returns_null_latest_when_unset(): void
    {
        app(AppReleaseService::class)->clearCache();

        $this->getJson('/api/v1/app/version')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latest_version', null);
    }

    public function test_latest_version_is_read_automatically_from_pubspec(): void
    {
        File::put($this->tempPubspec, "name: lamma_app\nversion: 1.0.2+6\n");
        app(AppReleaseService::class)->clearCache();

        $this->getJson('/api/v1/app/version')
            ->assertOk()
            ->assertJsonPath('data.latest_version', '1.0.2+6');
    }

    public function test_client_check_auto_bumps_latest_when_newer(): void
    {
        Setting::updateOrCreate(
            ['key' => 'app_latest_version'],
            ['value' => '1.0.1+5', 'type' => 'string']
        );
        app(AppReleaseService::class)->clearCache();

        $this->postJson('/api/v1/app/version/check', [
            'client_version' => '1.1.0+1',
        ])
            ->assertOk()
            ->assertJsonPath('data.latest_version', '1.1.0+1');

        $this->assertSame(
            '1.1.0+1',
            Setting::query()->where('key', 'app_latest_version')->value('value')
        );
    }

    public function test_client_check_does_not_downgrade_latest(): void
    {
        Setting::updateOrCreate(
            ['key' => 'app_latest_version'],
            ['value' => '1.1.0+1', 'type' => 'string']
        );
        app(AppReleaseService::class)->clearCache();

        $this->postJson('/api/v1/app/version/check', [
            'client_version' => '1.0.1+5',
        ])
            ->assertOk()
            ->assertJsonPath('data.latest_version', '1.1.0+1');
    }
}
