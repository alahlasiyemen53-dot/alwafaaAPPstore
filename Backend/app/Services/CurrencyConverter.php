<?php

namespace App\Services;

use App\Data\ConvertedPriceData;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use App\Support\Decimal;
use DateTimeInterface;
use RuntimeException;

final class CurrencyConverter
{
    private ?Currency $baseCurrency = null;

    /**
     * Request-scoped cache to prevent N+1 database queries during bulk conversions.
     *
     * @var array<string, array{rate: string, direction: 'target_to_base'|'base_to_target', effective_at: string}|null>
     */
    private array $resolvedRatesCache = [];

    /**
     * Clear the request-scoped cache (used when settings or exchange rates are updated).
     */
    public function clearCache(): void
    {
        $this->baseCurrency = null;
        $this->resolvedRatesCache = [];
    }

    /**
     * Get the single authoritative Base Currency for the application.
     * Primary source of truth is /admin/master-data/settings ('default_currency_id').
     * Fallback for unseeded test environments is Currency::where('is_default', true).
     */
    public function getBaseCurrency(): Currency
    {
        if ($this->baseCurrency !== null) {
            return $this->baseCurrency;
        }

        // 1. Primary Authority: System Setting 'default_currency_id'
        $settingCurrencyId = Setting::query()->where('key', 'default_currency_id')->value('value');
        if (! empty($settingCurrencyId)) {
            $currency = Currency::query()
                ->where('id', (int) $settingCurrencyId)
                ->where('is_active', true)
                ->first();

            if ($currency !== null) {
                return $this->baseCurrency = $currency;
            }
        }

        // 2. Compatibility Fallback: Currency with is_default flag
        $baseCurrencies = Currency::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->get();

        if ($baseCurrencies->isEmpty()) {
            throw new RuntimeException('لم يتم العثور على عملة أساسية نشطة للنظام.');
        }

        if ($baseCurrencies->count() > 1) {
            throw new RuntimeException('يوجد أكثر من عملة أساسية نشطة في النظام.');
        }

        return $this->baseCurrency = $baseCurrencies->first();
    }

    /**
     * Resolve exchange rate between Base Currency and Target Currency.
     * Authoritative rate semantic: 1 Target Currency = Rate Base Currency units.
     * e.g., 1 SAR = 140 YER -> Rate(SAR) = 140.00000000.
     *
     * @return array{rate: string, direction: 'target_to_base'|'base_to_target', effective_at: string}|null
     */
    public function resolveRate(Currency $fromCurrency, Currency $toCurrency, ?DateTimeInterface $effectiveAt = null): ?array
    {
        $targetDate = $effectiveAt ? $effectiveAt->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $cacheKey = "{$fromCurrency->id}_{$toCurrency->id}_{$targetDate}";

        if (array_key_exists($cacheKey, $this->resolvedRatesCache)) {
            return $this->resolvedRatesCache[$cacheKey];
        }

        // 1. Authoritative rate: from = target, to = base (1 Target = Rate Base)
        $rateRecord = ExchangeRate::query()
            ->where('from_currency_id', $fromCurrency->id)
            ->where('to_currency_id', $toCurrency->id)
            ->where('effective_at', '<=', $targetDate)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($rateRecord !== null && bccomp((string) $rateRecord->rate, '0', 8) > 0) {
            return $this->resolvedRatesCache[$cacheKey] = [
                'rate' => (string) $rateRecord->rate,
                'direction' => 'target_to_base',
                'effective_at' => (string) ($rateRecord->effective_at ?? $targetDate),
            ];
        }

        // 2. Inverse check if stored in reverse: from = toCurrency, to = fromCurrency
        $inverseRecord = ExchangeRate::query()
            ->where('from_currency_id', $toCurrency->id)
            ->where('to_currency_id', $fromCurrency->id)
            ->where('effective_at', '<=', $targetDate)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if ($inverseRecord !== null && bccomp((string) $inverseRecord->rate, '0', 8) > 0) {
            return $this->resolvedRatesCache[$cacheKey] = [
                'rate' => (string) $inverseRecord->rate,
                'direction' => 'base_to_target',
                'effective_at' => (string) ($inverseRecord->effective_at ?? $targetDate),
            ];
        }

        return $this->resolvedRatesCache[$cacheKey] = null;
    }

    /**
     * Convert an amount from Base Currency to Target Currency.
     * Formula: Base Amount / Target Rate
     * e.g., 10,000 YER / 140 = 71.43 SAR.
     */
    public function convertFromBase(
        string|int|float|null $baseAmount,
        Currency $targetCurrency,
        ?DateTimeInterface $effectiveAt = null,
        ?int $decimals = null,
    ): ConvertedPriceData {
        $baseCurrency = $this->getBaseCurrency();
        $isBase = $targetCurrency->id === $baseCurrency->id;

        if ($baseAmount === null || $baseAmount === '') {
            return ConvertedPriceData::unavailable(
                $targetCurrency->id,
                $targetCurrency->code,
                $targetCurrency->symbol ?? $targetCurrency->code,
                $isBase,
            );
        }

        $amountStr = Decimal::normalize($baseAmount);
        $targetDecimals = $decimals ?? $targetCurrency->decimal_places;

        // Base -> Base: No rate lookup needed
        if ($isBase) {
            $formattedAmount = number_format((float) $amountStr, $targetDecimals, '.', '');
            $displayFormatted = number_format((float) $amountStr, $targetDecimals, '.', ',').' '.($targetCurrency->symbol ?? $targetCurrency->code);

            return new ConvertedPriceData(
                currencyId: $targetCurrency->id,
                currencyCode: $targetCurrency->code,
                currencySymbol: $targetCurrency->symbol ?? $targetCurrency->code,
                amount: $formattedAmount,
                formatted: $displayFormatted,
                rateUsed: '1.00000000',
                effectiveAt: now()->format('Y-m-d H:i:s'),
                isAvailable: true,
                isBase: true,
            );
        }

        // Authoritative rate lookup: 1 Target = Rate Base
        // from = Target, to = Base
        $rateInfo = $this->resolveRate($targetCurrency, $baseCurrency, $effectiveAt);

        if ($rateInfo === null || bccomp($rateInfo['rate'], '0', 8) <= 0) {
            return ConvertedPriceData::unavailable(
                $targetCurrency->id,
                $targetCurrency->code,
                $targetCurrency->symbol ?? $targetCurrency->code,
                false,
            );
        }

        // Base -> Target: Base Amount / Target Rate
        // If rate was stored in inverse (direction === base_to_target): multiply
        if ($rateInfo['direction'] === 'target_to_base') {
            $convertedRaw = bcdiv($amountStr, $rateInfo['rate'], 8);
        } else {
            $convertedRaw = bcmul($amountStr, $rateInfo['rate'], 8);
        }

        $rounded = number_format((float) $convertedRaw, $targetDecimals, '.', '');
        $displayFormatted = number_format((float) $convertedRaw, $targetDecimals, '.', ',').' '.($targetCurrency->symbol ?? $targetCurrency->code);

        return new ConvertedPriceData(
            currencyId: $targetCurrency->id,
            currencyCode: $targetCurrency->code,
            currencySymbol: $targetCurrency->symbol ?? $targetCurrency->code,
            amount: $rounded,
            formatted: $displayFormatted,
            rateUsed: $rateInfo['rate'],
            effectiveAt: $rateInfo['effective_at'],
            isAvailable: true,
            isBase: false,
        );
    }

    /**
     * Convert an amount from Target/Source Currency to Base Currency.
     * Formula: Target Amount * Target Rate
     * e.g., 71.43 SAR * 140 = 10,000.20 YER.
     */
    public function convertToBase(
        string|int|float|null $targetAmount,
        Currency $sourceCurrency,
        ?DateTimeInterface $effectiveAt = null,
        ?int $decimals = null,
    ): ConvertedPriceData {
        $baseCurrency = $this->getBaseCurrency();
        $isBase = $sourceCurrency->id === $baseCurrency->id;

        if ($targetAmount === null || $targetAmount === '') {
            return ConvertedPriceData::unavailable(
                $baseCurrency->id,
                $baseCurrency->code,
                $baseCurrency->symbol ?? $baseCurrency->code,
                true,
            );
        }

        $amountStr = Decimal::normalize($targetAmount);
        $targetDecimals = $decimals ?? $baseCurrency->decimal_places;

        // Base -> Base: No rate lookup needed
        if ($isBase) {
            $formattedAmount = number_format((float) $amountStr, $targetDecimals, '.', '');
            $displayFormatted = number_format((float) $amountStr, $targetDecimals, '.', ',').' '.($baseCurrency->symbol ?? $baseCurrency->code);

            return new ConvertedPriceData(
                currencyId: $baseCurrency->id,
                currencyCode: $baseCurrency->code,
                currencySymbol: $baseCurrency->symbol ?? $baseCurrency->code,
                amount: $formattedAmount,
                formatted: $displayFormatted,
                rateUsed: '1.00000000',
                effectiveAt: now()->format('Y-m-d H:i:s'),
                isAvailable: true,
                isBase: true,
            );
        }

        // Authoritative rate lookup: 1 Source = Rate Base
        $rateInfo = $this->resolveRate($sourceCurrency, $baseCurrency, $effectiveAt);

        if ($rateInfo === null || bccomp($rateInfo['rate'], '0', 8) <= 0) {
            return ConvertedPriceData::unavailable(
                $baseCurrency->id,
                $baseCurrency->code,
                $baseCurrency->symbol ?? $baseCurrency->code,
                true,
            );
        }

        // Target -> Base: Target Amount * Target Rate
        if ($rateInfo['direction'] === 'target_to_base') {
            $convertedRaw = bcmul($amountStr, $rateInfo['rate'], 8);
        } else {
            $convertedRaw = bcdiv($amountStr, $rateInfo['rate'], 8);
        }

        $rounded = number_format((float) $convertedRaw, $targetDecimals, '.', '');
        $displayFormatted = number_format((float) $convertedRaw, $targetDecimals, '.', ',').' '.($baseCurrency->symbol ?? $baseCurrency->code);

        return new ConvertedPriceData(
            currencyId: $baseCurrency->id,
            currencyCode: $baseCurrency->code,
            currencySymbol: $baseCurrency->symbol ?? $baseCurrency->code,
            amount: $rounded,
            formatted: $displayFormatted,
            rateUsed: $rateInfo['rate'],
            effectiveAt: $rateInfo['effective_at'],
            isAvailable: true,
            isBase: true,
        );
    }

    /**
     * General conversion between any two currencies.
     */
    public function convert(
        string|int|float|null $amount,
        Currency $fromCurrency,
        Currency $toCurrency,
        ?DateTimeInterface $effectiveAt = null,
        ?int $decimals = null,
    ): ConvertedPriceData {
        $baseCurrency = $this->getBaseCurrency();

        // 1. Same currency
        if ($fromCurrency->id === $toCurrency->id) {
            $isBase = $toCurrency->id === $baseCurrency->id;
            $amountStr = Decimal::normalize($amount ?? '0');
            $targetDecimals = $decimals ?? $toCurrency->decimal_places;
            $formattedAmount = number_format((float) $amountStr, $targetDecimals, '.', '');
            $displayFormatted = number_format((float) $amountStr, $targetDecimals, '.', ',').' '.($toCurrency->symbol ?? $toCurrency->code);

            return new ConvertedPriceData(
                currencyId: $toCurrency->id,
                currencyCode: $toCurrency->code,
                currencySymbol: $toCurrency->symbol ?? $toCurrency->code,
                amount: $formattedAmount,
                formatted: $displayFormatted,
                rateUsed: '1.00000000',
                effectiveAt: now()->format('Y-m-d H:i:s'),
                isAvailable: true,
                isBase: $isBase,
            );
        }

        // 2. From Base -> Target
        if ($fromCurrency->id === $baseCurrency->id) {
            return $this->convertFromBase($amount, $toCurrency, $effectiveAt, $decimals);
        }

        // 3. From Target -> Base
        if ($toCurrency->id === $baseCurrency->id) {
            return $this->convertToBase($amount, $fromCurrency, $effectiveAt, $decimals);
        }

        // 4. Cross-currency: Target A -> Base -> Target B
        $toBase = $this->convertToBase($amount, $fromCurrency, $effectiveAt);
        if (! $toBase->isAvailable) {
            return ConvertedPriceData::unavailable(
                $toCurrency->id,
                $toCurrency->code,
                $toCurrency->symbol ?? $toCurrency->code,
                false,
            );
        }

        return $this->convertFromBase($toBase->amount, $toCurrency, $effectiveAt, $decimals);
    }

    /**
     * Alias for convertFromBase.
     */
    public function convertBase(
        string|int|float|null $amount,
        Currency $targetCurrency,
        ?DateTimeInterface $effectiveAt = null,
    ): ConvertedPriceData {
        return $this->convertFromBase($amount, $targetCurrency, $effectiveAt);
    }

    /**
     * @return array<string, ConvertedPriceData>
     */
    public function convertBaseToAllActive(
        string|int|float|null $amount,
        ?DateTimeInterface $effectiveAt = null,
    ): array {
        $activeCurrencies = Currency::query()->where('is_active', true)->orderBy('code')->get();
        $result = [];

        foreach ($activeCurrencies as $currency) {
            $result[$currency->code] = $this->convertFromBase($amount, $currency, $effectiveAt);
        }

        return $result;
    }
}
