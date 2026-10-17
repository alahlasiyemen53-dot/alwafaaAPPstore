<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CustomerDeliveryZoneController extends Controller
{
    /**
     * Public read-only Sana'a delivery polygon for map overlay / client pre-check.
     * Vertices use [longitude, latitude] — same order as config('delivery.sanaa_polygon').
     */
    public function sanaaZone(): JsonResponse
    {
        $polygon = config('delivery.sanaa_polygon');
        $vertices = [];

        if (is_array($polygon)) {
            foreach ($polygon as $point) {
                if (! is_array($point) || count($point) < 2) {
                    continue;
                }
                $longitude = $point[0] ?? null;
                $latitude = $point[1] ?? null;
                if (! is_numeric($longitude) || ! is_numeric($latitude)) {
                    continue;
                }
                $vertices[] = [(float) $longitude, (float) $latitude];
            }
        }

        $configured = count($vertices) >= 3;

        return ApiResponse::success(
            [
                'configured' => $configured,
                'name' => 'sanaa',
                'coordinate_order' => 'lng_lat',
                'vertices' => $configured ? $vertices : [],
            ],
            $configured
                ? 'تم جلب نطاق توصيل صنعاء بنجاح.'
                : 'نطاق توصيل صنعاء غير مُعد حالياً.'
        );
    }
}
