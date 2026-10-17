<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

final class GenerateDeliveryNumberAction
{
    public function execute(): string
    {
        $lastId = DB::table('deliveries')->max('id') ?? 0;
        $nextNumber = $lastId + 1;

        do {
            $candidate = sprintf('DEL-%06d', $nextNumber);
            $exists = DB::table('deliveries')->where('delivery_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
