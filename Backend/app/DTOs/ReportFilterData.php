<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ReportFilterData
{
    public function __construct(
        public ReportDateRangeData $range,
        public ?int $currencyId,
        public ?int $warehouseId,
        public ?int $customerId,
        public ?int $supplierId,
        public ?int $driverId,
        public string $sort,
        public string $direction,
        public int $perPage,
    ) {}
}
