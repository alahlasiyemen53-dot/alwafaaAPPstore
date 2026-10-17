<?php

namespace App\Actions;

use App\Models\PurchaseInvoice;
use App\Models\SupplierLedgerEntry;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class DeletePurchaseInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            /** @var PurchaseInvoice $lockedInvoice */
            $lockedInvoice = PurchaseInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            // Atomically delete associated supplier ledger entry if present
            SupplierLedgerEntry::query()
                ->where('reference_type', PurchaseInvoice::class)
                ->where('reference_id', $lockedInvoice->id)
                ->delete();

            $this->auditLogger->record('admin.purchase_invoice.deleted', $lockedInvoice, metadata: [
                'invoice_number' => $lockedInvoice->invoice_number,
                'supplier_id' => $lockedInvoice->supplier_id,
                'total' => $lockedInvoice->total,
                'status' => $lockedInvoice->status->value,
            ]);

            $lockedInvoice->items()->delete();
            $lockedInvoice->delete();
        });
    }
}
