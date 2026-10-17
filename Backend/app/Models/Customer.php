<?php

namespace App\Models;

use App\Enums\PhoneVerificationMethod;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'normalized_phone',
        'email',
        'is_active',
        'phone_verified_at',
        'phone_verification_method',
        'phone_verified_by',
        'notes',
        'current_tier_id',
        'country_id',
        'governorate_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'phone_verified_at' => 'datetime',
        'phone_verification_method' => PhoneVerificationMethod::class,
    ];

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    /** @return BelongsTo<User, $this> */
    public function phoneVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'phone_verified_by');
    }

    /** @return HasMany<CouponUsage, $this> */
    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /** @return HasOne<CustomerLoyaltyAccount, $this> */
    public function loyaltyAccount(): HasOne
    {
        return $this->hasOne(CustomerLoyaltyAccount::class);
    }

    /** @return HasMany<CustomerWalletAccount, $this> */
    public function walletAccounts(): HasMany
    {
        return $this->hasMany(CustomerWalletAccount::class);
    }

    /** @return MorphMany<PushDevice, $this> */
    public function pushDevices(): MorphMany
    {
        return $this->morphMany(PushDevice::class, 'owner');
    }

    /** @return MorphMany<BusinessNotification, $this> */
    public function businessNotifications(): MorphMany
    {
        return $this->morphMany(BusinessNotification::class, 'recipient');
    }

    /** @return HasMany<CustomerWishlistItem, $this> */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(CustomerWishlistItem::class);
    }

    /** @return HasMany<ProductReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /** @return BelongsTo<CustomerTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CustomerTier::class, 'current_tier_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Governorate, $this> */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /** @return HasMany<CustomerTierMembership, $this> */
    public function tierMemberships(): HasMany
    {
        return $this->hasMany(CustomerTierMembership::class);
    }

    /** @return HasOne<CustomerTierMembership, $this> */
    public function currentTierMembership(): HasOne
    {
        return $this->hasOne(CustomerTierMembership::class)->whereNull('ends_at');
    }

    public function getCurrentTier(): ?CustomerTier
    {
        if ($this->relationLoaded('tier') && $this->tier !== null) {
            return $this->tier;
        }

        if ($this->current_tier_id !== null) {
            return $this->tier;
        }

        return $this->currentTierMembership?->tier;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null && $this->phone_verification_method !== null;
    }

    public function canBeManuallyVerified(): bool
    {
        return ! $this->isPhoneVerified();
    }
}
