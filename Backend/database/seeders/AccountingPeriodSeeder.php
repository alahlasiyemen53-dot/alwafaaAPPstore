<?php

namespace Database\Seeders;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use Illuminate\Database\Seeder;

final class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        AccountingPeriod::firstOrCreate(
            [
                'starts_at' => '2026-01-01',
                'ends_at' => '2026-12-31',
            ],
            [
                'name' => 'السنة المالية 2026',
                'status' => AccountingPeriodStatus::Open,
            ]
        );
    }
}
