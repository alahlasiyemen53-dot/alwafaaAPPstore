<?php

namespace App\Actions;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteStockTransferAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $lockedTransfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($lockedTransfer->status !== StockTransferStatus::Draft) {
                throw new DomainException('لا يمكن حذف أمر تحويل مرحّل.');
            }

            $transferNumber = $lockedTransfer->transfer_number;

            $lockedTransfer->items()->delete();
            $lockedTransfer->delete();

            $this->auditLogger->log(
                action: 'stock_transfer.deleted',
                actor: auth()->user(),
                target: null,
                payload: ['transfer_number' => $transferNumber]
            );
        });
    }
}
