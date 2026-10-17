<?php

namespace App\Models;

use App\Enums\ProductVariantFulfillmentMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'sku_snapshot',
        'fulfillment_mode_snapshot',
        'sourcing_supplier_id_snapshot',
        'supplier_name_snapshot',
        'supplier_item_number_snapshot',
        'unit_id',
        'unit_name_snapshot',
        'promotion_id',
        'promotion_name_snapshot',
        'promotion_type_snapshot',
        'promotion_value_base_snapshot',
        'bundle_id',
        'bundle_name_snapshot',
        'bundle_code_snapshot',
        'bundle_pricing_type_snapshot',
        'bundle_value_base_snapshot',
        'quantity',
        'base_quantity',
        'original_unit_price',
        'unit_price',
        'line_subtotal',
        'discount_amount',
        'item_discount_amount',
        'allocated_order_discount',
        'line_total',
        'base_currency_unit_price',
        'base_currency_line_total',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'original_unit_price' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'item_discount_amount' => 'decimal:4',
        'allocated_order_discount' => 'decimal:4',
        'line_total' => 'decimal:4',
        'base_currency_unit_price' => 'decimal:4',
        'base_currency_line_total' => 'decimal:4',
        'promotion_value_base_snapshot' => 'decimal:4',
        'bundle_value_base_snapshot' => 'decimal:4',
        'fulfillment_mode_snapshot' => ProductVariantFulfillmentMode::class,
        'cancelled_at' => 'datetime',
    ];

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** @return HasOne<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): HasOne
    {
        return $this->hasOne(PurchaseOrderItem::class);
    }

    public function isStocked(): bool
    {
        return ($this->fulfillment_mode_snapshot ?? ProductVariantFulfillmentMode::Stocked) === ProductVariantFulfillmentMode::Stocked;
    }

    public function isOnDemand(): bool
    {
        return ($this->fulfillment_mode_snapshot ?? ProductVariantFulfillmentMode::Stocked) === ProductVariantFulfillmentMode::OnDemand;
    }

    /** @return BelongsTo<Supplier, $this> */
    public function sourcingSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'sourcing_supplier_id_snapshot');
    }

    /** @return HasOne<OrderSourcingRequirement, $this> */
    public function sourcingRequirement(): HasOne
    {
        return $this->hasOne(OrderSourcingRequirement::class, 'order_item_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->productVariant();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<Bundle, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /** @return HasOne<InventoryReservation, $this> */
    public function reservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    /** @return HasMany<SalesReturnItem, $this> */
    public function salesReturnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
