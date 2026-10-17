<?php

namespace App\Actions;

use App\Enums\StockAdjustmentStatus;
use App\Models\StockAdjustment;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteStockAdjustmentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment): void {
            $lockedAdjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if ($lockedAdjustment->status !== StockAdjustmentStatus::Draft) {
                throw new DomainException('لا يمكن حذف أمر تسوية مرحّل.');
            }

            $adjustmentNumber = $lockedAdjustment->adjustment_number;

            $lockedAdjustment->items()->delete();
            $lockedAdjustment->delete();

            $this->auditLogger->log(
                action: 'stock_adjustment.deleted',
                actor: auth()->user(),
                target: null,
                payload: ['adjustment_number' => $adjustmentNumber]
            );
        });
    }
}
