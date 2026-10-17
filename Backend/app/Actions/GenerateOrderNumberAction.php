<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

final class GenerateOrderNumberAction
{
    public function execute(): string
    {
        $lastId = DB::table('orders')->max('id') ?? 0;
        $nextNumber = $lastId + 1;

        // Ensure uniqueness even in high concurrency or reseeded database
        do {
            $candidate = sprintf('ORD-%06d', $nextNumber);
            $exists = DB::table('orders')->where('order_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
