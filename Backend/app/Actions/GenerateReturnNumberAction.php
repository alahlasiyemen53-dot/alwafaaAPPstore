<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

final class GenerateReturnNumberAction
{
    public function execute(): string
    {
        $lastId = DB::table('sales_returns')->max('id') ?? 0;
        $nextNumber = $lastId + 1;

        do {
            $candidate = sprintf('RET-%06d', $nextNumber);
            $exists = DB::table('sales_returns')->where('return_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
