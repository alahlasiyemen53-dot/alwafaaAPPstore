<?php

namespace App\Models;

use App\Enums\PurchaseInvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

final class PurchaseInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'warehouse_id',
        'currency_id',
        'supplier_invoice_number',
        'invoice_date',
        'status',
        'exchange_rate',
        'subtotal',
        'discount_amount',
        'other_costs',
        'total',
        'notes',
        'source_order_id',
        'purchase_order_id',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'status' => PurchaseInvoiceStatus::class,
        'exchange_rate' => 'decimal:8',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'other_costs' => 'decimal:4',
        'total' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return HasMany<PurchaseInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return MorphOne<SupplierLedgerEntry, $this> */
    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(SupplierLedgerEntry::class, 'reference');
    }

    /** @return HasMany<PurchaseReceipt, $this> */
    public function purchaseReceipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Posted;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Cancelled;
    }
}
