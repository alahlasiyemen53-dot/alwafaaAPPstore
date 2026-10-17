<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

final class GenerateDriverCodeAction
{
    public function execute(): string
    {
        $lastId = DB::table('drivers')->max('id') ?? 0;
        $nextNumber = $lastId + 1;

        do {
            $candidate = sprintf('DRV-%04d', $nextNumber);
            $exists = DB::table('drivers')->where('code', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
