<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy one-time backfill helper used by the pivot migration.
 * New governorate/method pairs must be created explicitly via admin UI with an explicit fee.
 */
final class GovernorateDeliveryMethodBackfill
{
    public static function run(): void
    {
        if (! self::tablesReady()) {
            return;
        }

        $hasLegacyFee = Schema::hasColumn('governorates', 'delivery_fee');

        DB::transaction(function () use ($hasLegacyFee): void {
            $methods = DeliveryMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'sort_order']);

            if ($methods->isEmpty()) {
                return;
            }

            $select = $hasLegacyFee ? ['id', 'delivery_fee'] : ['id'];

            Governorate::query()
                ->select($select)
                ->orderBy('id')
                ->chunkById(100, function ($governorates) use ($methods, $hasLegacyFee): void {
                    foreach ($governorates as $governorate) {
                        foreach ($methods as $method) {
                            GovernorateDeliveryMethod::query()->firstOrCreate(
                                [
                                    'governorate_id' => $governorate->id,
                                    'delivery_method_id' => $method->id,
                                ],
                                [
                                    'delivery_fee' => $hasLegacyFee ? (float) $governorate->delivery_fee : 0,
                                    'is_active' => true,
                                    'sort_order' => (int) $method->sort_order,
                                ]
                            );
                        }
                    }
                });
        });
    }

    private static function tablesReady(): bool
    {
        return Schema::hasTable('governorate_delivery_methods')
            && Schema::hasTable('governorates')
            && Schema::hasTable('delivery_methods');
    }
}
