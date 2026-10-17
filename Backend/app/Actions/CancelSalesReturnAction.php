<?php

namespace App\Actions;

use App\Enums\SalesReturnStatus;
use App\Models\SalesReturn;
use DomainException;

final class CancelSalesReturnAction
{
    public function execute(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if (! $salesReturn->canBeCancelled()) {
            throw new DomainException('لا يمكن إلغاء المرتجع إلا إذا كان في حالة مسودة.');
        }

        $salesReturn->update([
            'status' => SalesReturnStatus::Cancelled,
            'updated_by' => $userId,
        ]);

        return $salesReturn->fresh();
    }
}
