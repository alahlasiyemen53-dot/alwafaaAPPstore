<?php

namespace App\Actions;

use App\DTOs\SalesReturnData;
use App\Enums\SalesReturnStatus;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateSalesReturnAction
{
    public function __construct(
        private readonly GenerateReturnNumberAction $generateNumber,
        private readonly CalculateSalesReturnTotalsAction $calculateTotals,
    ) {}

    public function execute(SalesReturnData $data, int $userId): SalesReturn
    {
        $warehouse = Warehouse::query()->findOrFail($data->warehouseId);
        if (! $warehouse->is_active) {
            throw new DomainException('المستودع المحدد للاستلام غير نشط.');
        }

        $calculated = $this->calculateTotals->execute($data->orderId, $data->items);
        $order = $calculated['order'];

        return DB::transaction(function () use ($data, $userId, $calculated, $order) {
            $returnNumber = $this->generateNumber->execute();

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'order_id' => $order->id,
                'warehouse_id' => $data->warehouseId,
                'status' => SalesReturnStatus::Draft,
                'reason_code' => $data->reasonCode,
                'notes' => $data->notes,
                'currency_id' => $order->currency_id,
                'exchange_rate_snapshot' => $order->exchange_rate_snapshot,
                'subtotal' => $calculated['subtotal'],
                'total' => $calculated['total'],
                'base_currency_total' => $calculated['base_currency_total'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($calculated['items'] as $item) {
                $salesReturn->items()->create($item);
            }

            return $salesReturn->load(['items', 'order', 'warehouse', 'currency']);
        });
    }
}
