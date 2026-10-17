<?php

namespace App\Contracts\Delivery;

interface ServiceAreaValidator
{
    public function isConfigured(): bool;

    public function contains(float $latitude, float $longitude): bool;
}
