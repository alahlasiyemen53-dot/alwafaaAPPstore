<?php

namespace App\DTOs;

final readonly class ReverseJournalEntryData
{
    public function __construct(
        public string $reason,
        public string $reversalDate,
        public ?int $accountingPeriodId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reason: trim((string) $data['reason']),
            reversalDate: trim((string) $data['reversal_date']),
            accountingPeriodId: ! empty($data['accounting_period_id']) ? (int) $data['accounting_period_id'] : null,
        );
    }
}
