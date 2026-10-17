<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class CountryCallingCode
{
    public static function default(): string
    {
        if (! self::canUseDatabase()) {
            return '+967';
        }

        return Cache::remember('country.default_calling_code', 3600, function (): string {
            $country = Country::defaultActive();

            return $country?->calling_code ?? '+967';
        });
    }

    public static function forCountryId(?int $countryId): string
    {
        if ($countryId === null || $countryId <= 0) {
            return self::default();
        }

        if (! self::canUseDatabase()) {
            return '+967';
        }

        return Cache::remember("country.calling_code.{$countryId}", 3600, function () use ($countryId): string {
            $callingCode = Country::query()
                ->where('id', $countryId)
                ->where('is_active', true)
                ->value('calling_code');

            return is_string($callingCode) && $callingCode !== ''
                ? $callingCode
                : self::default();
        });
    }

    public static function forgetCache(?int $countryId = null): void
    {
        if (! self::canUseDatabase()) {
            return;
        }

        Cache::forget('country.default_calling_code');
        Cache::forget('country.reference.list');

        if ($countryId !== null && $countryId > 0) {
            Cache::forget("country.calling_code.{$countryId}");
        }
    }

    private static function canUseDatabase(): bool
    {
        try {
            return function_exists('app')
                && app()->bound('db')
                && Schema::hasTable('countries');
        } catch (\Throwable) {
            return false;
        }
    }
}
