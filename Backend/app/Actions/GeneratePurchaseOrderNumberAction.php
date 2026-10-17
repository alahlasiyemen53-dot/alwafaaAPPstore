<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;

final class GeneratePurchaseOrderNumberAction
{
    public function execute(): string
    {
        $lastId = DB::table('purchase_orders')->max('id') ?? 0;
        $nextNumber = $lastId + 1;

        do {
            $candidate = sprintf('PO-%06d', $nextNumber);
            $exists = DB::table('purchase_orders')->where('po_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}
