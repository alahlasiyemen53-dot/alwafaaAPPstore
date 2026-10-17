<?php

namespace App\DTOs;

final readonly class CreateJournalEntryData
{
    /**
     * @param  array<JournalEntryLineData>  $lines
     */
    public function __construct(
        public int $accountingPeriodId,
        public string $entryDate,
        public string $description,
        public int $currencyId,
        public float|string $exchangeRateSnapshot = '1.00000000',
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $sourceEvent = null,
        public array $lines = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawLines = $data['lines'] ?? [];
        $lines = [];
        $order = 1;

        foreach ($rawLines as $line) {
            if ($line instanceof JournalEntryLineData) {
                $lines[] = $line;
            } elseif (is_array($line)) {
                $line['line_order'] = $line['line_order'] ?? $order++;
                $line['transaction_currency_id'] = $line['transaction_currency_id'] ?? $data['currency_id'] ?? null;
                $line['exchange_rate_snapshot'] = $line['exchange_rate_snapshot'] ?? $data['exchange_rate_snapshot'] ?? '1';
                $lines[] = JournalEntryLineData::fromArray($line);
            }
        }

        return new self(
            accountingPeriodId: (int) $data['accounting_period_id'],
            entryDate: trim((string) $data['entry_date']),
            description: trim((string) $data['description']),
            currencyId: (int) $data['currency_id'],
            exchangeRateSnapshot: (string) ($data['exchange_rate_snapshot'] ?? '1.00000000'),
            sourceType: ! empty($data['source_type']) ? (string) $data['source_type'] : null,
            sourceId: ! empty($data['source_id']) ? (int) $data['source_id'] : null,
            sourceEvent: ! empty($data['source_event']) ? (string) $data['source_event'] : null,
            lines: $lines,
        );
    }
}
