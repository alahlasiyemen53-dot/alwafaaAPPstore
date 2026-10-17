<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerProofController extends Controller
{
    /**
     * Public ping endpoint.
     */
    public function ping(): JsonResponse
    {
        return ApiResponse::success([
            'pong' => true,
            'timestamp' => now()->toIso8601String(),
            'version' => 'v1',
        ], 'Customer API v1 is alive.');
    }

    /**
     * Authenticated customer identity endpoint.
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user('customer_jwt');

        return ApiResponse::success([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'is_active' => (bool) $customer->is_active,
            'phone_verified' => $customer->isPhoneVerified(),
            'created_at' => $customer->created_at?->toIso8601String(),
        ], 'Customer profile retrieved successfully.');
    }

    /**
     * Echo test endpoint with validation and idempotency support.
     */
    public function echo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'counter' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success([
            'echo' => $validated,
            'processed_at' => now()->toIso8601String(),
        ], 'Echo processed successfully.', 200);
    }

    /**
     * HTTP 204 No-Content proof endpoint.
     */
    public function empty(): Response
    {
        return ApiResponse::noContent();
    }
}
