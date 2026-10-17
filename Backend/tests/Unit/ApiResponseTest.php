<?php

namespace Tests\Unit;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response_has_the_project_contract(): void
    {
        $response = ApiResponse::success(['status' => 'ok']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'status' => true,
            'success' => true,
            'message' => null,
            'data' => ['status' => 'ok'],
        ], $response->getData(true));
    }

    public function test_error_response_has_the_project_contract(): void
    {
        $response = ApiResponse::error('Nope', ['field' => ['Invalid.']], 422);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'status' => false,
            'success' => false,
            'message' => 'Nope',
            'data' => [
                'code' => 'VALIDATION_FAILED',
                'errors' => ['field' => ['Invalid.']],
            ],
            'errors' => ['field' => ['Invalid.']],
        ], $response->getData(true));
    }
}
