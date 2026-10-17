<?php

namespace App\Data;

final readonly class ConvertedPriceData
{
    public function __construct(
        public int $currencyId,
        public string $currencyCode,
        public string $currencySymbol,
        public ?string $amount,
        public string $formatted,
        public ?string $rateUsed,
        public ?string $effectiveAt,
        public bool $isAvailable,
        public bool $isBase,
    ) {}

    public static function unavailable(
        int $currencyId,
        string $currencyCode,
        string $currencySymbol,
        bool $isBase = false,
    ): self {
        return new self(
            currencyId: $currencyId,
            currencyCode: $currencyCode,
            currencySymbol: $currencySymbol,
            amount: null,
            formatted: 'غير متاح',
            rateUsed: null,
            effectiveAt: null,
            isAvailable: false,
            isBase: $isBase,
        );
    }
}
