<?php

namespace App\Support;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * Standard JSON success response.
     * HTTP 200 / 201 etc.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $status, $headers);
    }

    /**
     * Standard resource creation response (HTTP 201).
     */
    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully.',
        array $headers = []
    ): JsonResponse {
        return self::success($data, $message, 201, $headers);
    }

    /**
     * Standard No-Body response (HTTP 204).
     * Must NOT contain a JSON body or envelope.
     */
    public static function noContent(array $headers = []): Response
    {
        return response()->noContent(204, $headers);
    }

    /**
     * Standard JSON error response with mandatory machine-readable code in data.code.
     */
    public static function error(
        string $message,
        ApiErrorCode|string|array $code = ApiErrorCode::InternalError,
        int $status = 400,
        array $errors = [],
        array $headers = []
    ): JsonResponse {
        // Backward-compatibility: if 2nd param is array of errors
        if (is_array($code)) {
            $errors = $code;
            $code = ApiErrorCode::ValidationFailed;
        }

        $codeString = $code instanceof ApiErrorCode ? $code->value : (string) $code;

        $data = ['code' => $codeString];
        if (! empty($errors)) {
            $data['errors'] = $errors;
        }

        $payload = [
            'status' => false,
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * Standard Validation failure response (HTTP 422).
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed.',
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'success' => false,
            'message' => $message,
            'data' => [
                'code' => ApiErrorCode::ValidationFailed->value,
                'errors' => $errors,
            ],
            'errors' => $errors,
        ], 422, $headers);
    }
}
