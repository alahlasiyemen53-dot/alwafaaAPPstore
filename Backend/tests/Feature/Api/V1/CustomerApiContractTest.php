<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_endpoint_returns_standard_success_envelope(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.pong', true)
            ->assertJsonPath('data.version', 'v1');
    }

    public function test_request_id_is_propagated_if_supplied_by_client(): void
    {
        $customRequestId = (string) Str::uuid();

        $response = $this->withHeaders([
            'X-Request-Id' => $customRequestId,
        ])->getJson('/api/v1/ping');

        $response->assertOk()
            ->assertHeader('X-Request-Id', $customRequestId);
    }

    public function test_no_content_endpoint_returns_empty_body(): void
    {
        $response = $this->get('/api/v1/proof/empty');

        $response->assertNoContent();
        $this->assertEmpty($response->getContent());
    }

    public function test_validation_failure_returns_canonical_envelope_and_error_code(): void
    {
        $response = $this->postJson('/api/v1/proof/echo', [
            // 'message' is missing intentionally
            'counter' => 'invalid-not-integer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'code',
                    'errors' => ['message', 'counter'],
                ],
            ]);
    }

    public function test_not_found_endpoint_returns_canonical_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/customer/non-existent-endpoint');

        $response->assertNotFound()
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_method_not_allowed_returns_canonical_error_envelope(): void
    {
        $response = $this->deleteJson('/api/v1/ping');

        $response->assertStatus(405)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_content_negotiation_rejects_unsupported_media_type(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/proof/echo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/xml',
                'HTTP_ACCEPT' => 'application/json',
            ],
            '<xml>data</xml>'
        );

        $response->assertStatus(415)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_content_negotiation_rejects_unacceptable_accept_header(): void
    {
        $response = $this->get('/api/v1/ping', [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(406)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'NOT_ACCEPTABLE');
    }
}
