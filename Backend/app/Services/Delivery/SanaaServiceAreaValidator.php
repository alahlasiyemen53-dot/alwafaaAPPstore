<?php

namespace App\Services\Delivery;

use App\Contracts\Delivery\ServiceAreaValidator;
use LogicException;

final class SanaaServiceAreaValidator implements ServiceAreaValidator
{
    public function isConfigured(): bool
    {
        return is_array(config('delivery.sanaa_polygon')) && count(config('delivery.sanaa_polygon')) >= 3;
    }

    public function contains(float $latitude, float $longitude): bool
    {
        $polygon = config('delivery.sanaa_polygon');
        if (! is_array($polygon) || count($polygon) < 3) {
            throw new LogicException('Sana’a delivery boundary is not configured.');
        }

        $inside = false;
        $lastIndex = count($polygon) - 1;
        for ($index = 0; $index < count($polygon); $lastIndex = $index++) {
            [$longitudeA, $latitudeA] = $polygon[$index];
            [$longitudeB, $latitudeB] = $polygon[$lastIndex];
            $intersects = (($latitudeA > $latitude) !== ($latitudeB > $latitude))
                && ($longitude < ($longitudeB - $longitudeA) * ($latitude - $latitudeA) / ($latitudeB - $latitudeA) + $longitudeA);
            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
