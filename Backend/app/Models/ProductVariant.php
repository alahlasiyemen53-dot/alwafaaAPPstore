<?php

namespace App\Models;

use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property ProductVariantStatus $status
 * @property ProductVariantFulfillmentMode $fulfillment_mode
 * @property int|null $supplier_id
 * @property string|null $supplier_item_number
 * @property-read Product $product
 * @property-read Supplier|null $supplier
 */
final class ProductVariant extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'status',
        'is_default',
        'fulfillment_mode',
        'supplier_id',
        'supplier_item_number',
        'cost_price',
    ];

    protected $casts = [
        'status' => ProductVariantStatus::class,
        'fulfillment_mode' => ProductVariantFulfillmentMode::class,
        'is_default' => 'boolean',
        'cost_price' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $variant): void {
            if ($variant->isDirty('sku') && $variant->getOriginal('sku') !== null) {
                $variant->sku = $variant->getOriginal('sku');
            }
        });
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function setSupplierItemNumberAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['supplier_item_number'] = null;

            return;
        }

        $trimmed = trim($value);
        $this->attributes['supplier_item_number'] = $trimmed === '' ? null : mb_substr($trimmed, 0, 100);
    }

    public function isStocked(): bool
    {
        return ($this->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked) === ProductVariantFulfillmentMode::Stocked;
    }

    public function isOnDemand(): bool
    {
        return ($this->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked) === ProductVariantFulfillmentMode::OnDemand;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_values')
            ->withPivot('attribute_id');
    }

    /** @return HasMany<VariantPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class);
    }

    /** @return HasMany<MediaEmbedding, $this> */
    public function embeddings(): HasMany
    {
        return $this->hasMany(MediaEmbedding::class, 'product_variant_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('variant_images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->quality(75)
            ->width(150)
            ->height(150);

        $this->addMediaConversion('small')
            ->nonQueued()
            ->format('webp')
            ->quality(80)
            ->width(400);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->format('webp')
            ->quality(82)
            ->width(800);

        $this->addMediaConversion('large')
            ->nonQueued()
            ->format('webp')
            ->quality(85)
            ->width(1200);
    }

    public function getPrimaryMedia(): ?Media
    {
        /** @var Collection<int, Media> $mediaList */
        $mediaList = $this->getMedia('variant_images');

        return $mediaList->first(fn (Media $media) => (bool) $media->getCustomProperty('is_primary', false))
            ?? $mediaList->first();
    }

    public function getPrimaryImageUrl(string $conversion = ''): ?string
    {
        $primary = $this->getPrimaryMedia();

        if ($primary) {
            return $primary->getUrl($conversion);
        }

        // Fallback to parent product's primary image
        $product = $this->relationLoaded('product')
            ? $this->product
            : $this->product()->first();

        if ($product instanceof Product) {
            return $product->getPrimaryImageUrl($conversion);
        }

        return null;
    }

    /** @return HasMany<Inventory, $this> */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<PurchaseReceiptItem, $this> */
    public function purchaseReceiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
