<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourcingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'warehouse_id',
        'currency_id',
        'coupon_id',
        'coupon_code_snapshot',
        'coupon_name_snapshot',
        'coupon_discount_type_snapshot',
        'coupon_discount_value_base_snapshot',
        'status',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'subtotal',
        'discount_amount',
        'delivery_fee',
        'promotion_discount_total',
        'coupon_discount_total',
        'bundle_discount_total',
        'total',
        'exchange_rate_snapshot',
        'base_currency_total',
        'base_currency_delivery_fee',
        'notes',
        'gift_wrapping_requested',
        'gift_message_snapshot',
        'shipping_method_code',
        'shipping_method_name_ar',
        'shipping_method_name_en',
        'payment_method_code',
        'payment_status',
        'payment_provider_reference',
        'payment_provider_request_id',
        'treasury_transaction_id',
        'cancellation_reason',
        'confirmed_at',
        'confirmed_by',
        'pickup_driver_id',
        'customer_confirm_deadline_at',
        'customer_confirmed_at',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'gift_wrapping_requested' => false,
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => OrderPaymentStatus::class,
        'gift_wrapping_requested' => 'boolean',
        'gift_message_snapshot' => 'string',
        'coupon_discount_value_base_snapshot' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'delivery_fee' => 'decimal:4',
        'promotion_discount_total' => 'decimal:4',
        'coupon_discount_total' => 'decimal:4',
        'bundle_discount_total' => 'decimal:4',
        'total' => 'decimal:4',
        'exchange_rate_snapshot' => 'decimal:8',
        'base_currency_total' => 'decimal:4',
        'base_currency_delivery_fee' => 'decimal:4',
        'confirmed_at' => 'datetime',
        'customer_confirm_deadline_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /** @return BelongsTo<TreasuryTransaction, $this> */
    public function treasuryTransaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<OrderAddress, $this> */
    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('address_type', 'delivery');
    }

    /** @return HasMany<OrderAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return HasMany<SalesReturn, $this> */
    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @return HasMany<OrderSourcingRequirement, $this> */
    public function sourcingRequirements(): HasMany
    {
        return $this->hasMany(OrderSourcingRequirement::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function pickupDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'pickup_driver_id');
    }

    public function hasOnDemandItems(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->isOnDemand() && $item->cancelled_at === null);
    }

    public function allOnDemandSourcingCollected(): bool
    {
        if ($this->relationLoaded('purchaseOrders') || $this->purchaseOrders()->exists()) {
            return $this->allActivePurchaseOrdersCollected();
        }

        $requirements = $this->sourcingRequirements;
        if ($requirements->isEmpty()) {
            return true;
        }

        return $requirements->every(fn (OrderSourcingRequirement $r) => $r->status === SourcingStatus::Collected);
    }

    public function allActivePurchaseOrdersCollected(): bool
    {
        $purchaseOrders = $this->relationLoaded('purchaseOrders')
            ? $this->purchaseOrders
            : $this->purchaseOrders()->get();

        $active = $purchaseOrders->filter(
            fn (PurchaseOrder $po): bool => $po->status->isActive()
        );

        if ($active->isEmpty()) {
            return true;
        }

        return $active->every(fn (PurchaseOrder $po): bool => $po->status === PurchaseOrderStatus::Collected);
    }

    public function purchaseOrdersCollectionProgress(): array
    {
        $purchaseOrders = $this->relationLoaded('purchaseOrders')
            ? $this->purchaseOrders
            : $this->purchaseOrders()->get();

        $active = $purchaseOrders->filter(fn (PurchaseOrder $po): bool => $po->status->isActive());
        $collected = $active->filter(fn (PurchaseOrder $po): bool => $po->status === PurchaseOrderStatus::Collected);

        return [
            'total' => $active->count(),
            'collected' => $collected->count(),
        ];
    }

    /** @return HasOne<Delivery, $this> */
    public function activeDelivery(): HasOne
    {
        return $this->hasOne(Delivery::class)->where('status', 'assigned');
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

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status->canBeEdited();
    }

    public function canBeConfirmed(): bool
    {
        return $this->status->canBeConfirmed();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function canBeAssignedToDriver(): bool
    {
        return $this->status->canBeAssignedToDriver();
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return HasMany<CouponUsage, $this> */
    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function hasGiftWrapping(): bool
    {
        return (bool) $this->gift_wrapping_requested;
    }

    public function hasGiftMessage(): bool
    {
        return $this->hasGiftWrapping() && ! empty(trim((string) $this->gift_message_snapshot));
    }

    public function paymentMethodLabel(): string
    {
        return CustomerPaymentMethod::presentationForCode($this->payment_method_code)['name_ar'];
    }

    public function paymentStatusOrDefault(): OrderPaymentStatus
    {
        return $this->payment_status ?? OrderPaymentStatus::Unpaid;
    }
}
