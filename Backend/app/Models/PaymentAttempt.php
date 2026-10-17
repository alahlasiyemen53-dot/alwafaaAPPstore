<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentProviderCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class PaymentAttempt extends Model
{
    protected $fillable = [
        'uuid',
        'customer_id',
        'customer_payment_method_id',
        'provider_code',
        'status',
        'provider_request_id',
        'provider_purchase_id',
        'provider_reference',
        'customer_phone',
        'amount',
        'currency_code',
        'customer_address_id',
        'shipping_method',
        'gift_wrapping_requested',
        'gift_message',
        'coupon_code',
        'notes',
        'provider_payload',
        'failure_message',
        'order_id',
        'expires_at',
        'confirmed_at',
    ];

    protected $casts = [
        'provider_code' => PaymentProviderCode::class,
        'status' => PaymentAttemptStatus::class,
        'amount' => 'decimal:4',
        'gift_wrapping_requested' => 'boolean',
        'provider_payload' => 'array',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            if ($attempt->uuid === null || $attempt->uuid === '') {
                $attempt->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerPaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentMethod::class, 'customer_payment_method_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<CustomerAddress, $this> */
    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
