<?php

namespace App\Actions;

use App\DTOs\SalesReturnData;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateDraftSalesReturnAction
{
    public function __construct(
        private readonly CalculateSalesReturnTotalsAction $calculateTotals,
    ) {}

    public function execute(SalesReturn $salesReturn, SalesReturnData $data, int $userId): SalesReturn
    {
        if (! $salesReturn->canBeEdited()) {
            throw new DomainException('لا يمكن تعديل المرتجع لأنه ليس في حالة مسودة.');
        }

        $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
        if (! $warehouse->is_active) {
            throw new DomainException('المستودع المحدد للاستلام غير نشط.');
        }

        $calculated = $this->calculateTotals->execute($data->orderId, $data->items, $salesReturn->id);
        $order = $calculated['order'];

        return DB::transaction(function () use ($salesReturn, $data, $userId, $calculated, $order) {
            $salesReturn->update([
                'order_id' => $order->id,
                'warehouse_id' => $data->warehouseId,
                'reason_code' => $data->reasonCode,
                'notes' => $data->notes,
                'currency_id' => $order->currency_id,
                'exchange_rate_snapshot' => $order->exchange_rate_snapshot,
                'subtotal' => $calculated['subtotal'],
                'total' => $calculated['total'],
                'base_currency_total' => $calculated['base_currency_total'],
                'updated_by' => $userId,
            ]);

            // Recreate items
            $salesReturn->items()->delete();
            foreach ($calculated['items'] as $item) {
                $salesReturn->items()->create($item);
            }

            return $salesReturn->fresh(['items', 'order', 'warehouse', 'currency']);
        });
    }
}
