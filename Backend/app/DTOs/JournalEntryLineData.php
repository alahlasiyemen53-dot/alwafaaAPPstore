<?php

namespace App\DTOs;

final readonly class JournalEntryLineData
{
    public function __construct(
        public int $accountId,
        public ?string $description,
        public float|string $debit,
        public float|string $credit,
        public int $transactionCurrencyId,
        public float|string $transactionAmount,
        public float|string $exchangeRateSnapshot,
        public float|string $baseCurrencyAmount,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public int $lineOrder = 1,
        public ?int $treasuryCurrencyAccountId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) $data['account_id'],
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            debit: (string) ($data['debit'] ?? '0'),
            credit: (string) ($data['credit'] ?? '0'),
            transactionCurrencyId: (int) ($data['transaction_currency_id'] ?? $data['currency_id']),
            transactionAmount: (string) ($data['transaction_amount'] ?? '0'),
            exchangeRateSnapshot: (string) ($data['exchange_rate_snapshot'] ?? '1'),
            baseCurrencyAmount: (string) ($data['base_currency_amount'] ?? '0'),
            partyType: ! empty($data['party_type']) ? (string) $data['party_type'] : null,
            partyId: ! empty($data['party_id']) ? (int) $data['party_id'] : null,
            lineOrder: (int) ($data['line_order'] ?? 1),
            treasuryCurrencyAccountId: ! empty($data['treasury_currency_account_id'])
                ? (int) $data['treasury_currency_account_id']
                : null,
        );
    }
}
