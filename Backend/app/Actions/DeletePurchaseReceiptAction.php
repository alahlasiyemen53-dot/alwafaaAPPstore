<?php

namespace App\Actions;

use App\Enums\PurchaseReceiptStatus;
use App\Models\PurchaseReceipt;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseReceiptAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $lockedReceipt = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($lockedReceipt->status !== PurchaseReceiptStatus::Draft) {
                throw new DomainException('لا يمكن حذف سند استلام مرحّل.');
            }

            $receiptNumber = $lockedReceipt->receipt_number;

            $lockedReceipt->items()->delete();
            $lockedReceipt->delete();

            $this->auditLogger->log(
                action: 'purchase_receipt.deleted',
                actor: auth()->user(),
                target: null,
                payload: ['receipt_number' => $receiptNumber]
            );
        });
    }
}
