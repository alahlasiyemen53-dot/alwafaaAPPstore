<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FoundationProbeJob;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_standard_success_contract(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonMissingPath('data.APP_KEY');
    }

    public function test_api_validation_returns_standard_error_contract(): void
    {
        $response = $this->getJson('/api/v1/health?verbose=not-a-boolean');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_missing_api_route_returns_safe_json_error(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    }

    public function test_queue_job_can_be_dispatched(): void
    {
        Queue::fake();

        dispatch(new FoundationProbeJob);

        Queue::assertPushed(FoundationProbeJob::class);
    }

    public function test_public_storage_disk_is_available(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('foundation/probe.txt', 'ok');

        Storage::disk('public')->assertExists('foundation/probe.txt');
    }
}
