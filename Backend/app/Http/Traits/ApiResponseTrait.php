<?php

namespace App\Http\Traits;

use App\Enums\ApiErrorCode;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponseTrait
{
    protected function respondSuccess(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status, $headers);
    }

    protected function respondCreated(
        mixed $data = null,
        string $message = 'Resource created successfully.',
        array $headers = []
    ): JsonResponse {
        return ApiResponse::created($data, $message, $headers);
    }

    protected function respondNoContent(array $headers = []): Response
    {
        return ApiResponse::noContent($headers);
    }

    protected function respondError(
        string $message,
        ApiErrorCode|string $code = ApiErrorCode::InternalError,
        int $status = 400,
        array $errors = [],
        array $headers = []
    ): JsonResponse {
        return ApiResponse::error($message, $code, $status, $errors, $headers);
    }

    protected function respondValidationFailed(
        array $errors,
        string $message = 'بيانات الإدخال غير صالحة. يرجى مراجعة الحقول المحددة.',
        array $headers = []
    ): JsonResponse {
        return ApiResponse::validationError($errors, $message, $headers);
    }
}
