<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contracts\Search\VisualProductSearchInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\VisualSearchCustomerProductsRequest;
use App\Http\Resources\Api\V1\Catalog\CustomerProductListResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CustomerVisualSearchController extends Controller
{
    public function __construct(
        private readonly VisualProductSearchInterface $visualSearch,
    ) {}

    public function search(VisualSearchCustomerProductsRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $temporaryDisk = Storage::disk('local');
        $temporaryPath = null;

        try {
            $temporaryPath = $file?->store('visual-search', 'local');
            if (! is_string($temporaryPath)) {
                throw new RuntimeException('Unable to store the visual search upload.');
            }

            $tempPath = $temporaryDisk->path($temporaryPath);
            $limit = (int) $request->input('limit', 20);
            $minSimilarity = (float) $request->input('min_similarity', 0.60);

            $result = $this->visualSearch->searchByImage(
                imagePathOrUrl: $tempPath,
                limit: $limit,
                minSimilarity: $minSimilarity
            );

            return response()->json([
                'success' => true,
                'message' => 'تم العثور على نتائج مطابقة للبحث المرئي',
                'data' => [
                    'items' => CustomerProductListResource::collection($result->items),
                    'total_matches' => $result->items->count(),
                    'total_candidates' => $result->totalCandidates,
                    'processing_time_ms' => $result->processingTimeMs,
                    'model_version' => $result->modelVersion,
                ],
            ], JsonResponse::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Customer visual search execution failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خدمة البحث المرئي غير متاحة حالياً، يرجى المحاولة لاحقاً',
                'code' => 'VISUAL_SEARCH_UNAVAILABLE',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        } finally {
            if ($temporaryPath !== null) {
                $temporaryDisk->delete($temporaryPath);
            }
        }
    }
}
