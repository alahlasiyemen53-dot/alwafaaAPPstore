<?php

namespace App\Models;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @property ProductStatus $status */
final class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'brand_id',
        'category_id',
        'supplier_id',
        'supplier_item_number',
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'status',
        'has_variants',
        'is_best_seller',
        'is_new',
        'is_exclusive',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'has_variants' => 'boolean',
        'is_best_seller' => 'boolean',
        'is_new' => 'boolean',
        'is_exclusive' => 'boolean',
    ];

    protected $attributes = [
        'has_variants' => false,
        'is_best_seller' => false,
        'is_new' => false,
        'is_exclusive' => false,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $product): void {
            if ($product->isDirty('slug') && $product->getOriginal('slug') !== null) {
                $product->slug = $product->getOriginal('slug');
            }
        });
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

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopeBestSeller(\Illuminate\Database\Eloquent\Builder $query, bool $value = true): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_best_seller', $value);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopeNewMerchandising(\Illuminate\Database\Eloquent\Builder $query, bool $value = true): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_new', $value);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopeExclusive(\Illuminate\Database\Eloquent\Builder $query, bool $value = true): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_exclusive', $value);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasOne<ProductVariant, $this> */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /** @return HasMany<MediaEmbedding, $this> */
    public function embeddings(): HasMany
    {
        return $this->hasMany(MediaEmbedding::class);
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

    /** @return HasMany<ProductReview, $this> */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', ProductReviewStatus::Approved);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_images');
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
        $mediaList = $this->getMedia('product_images');

        return $mediaList->first(fn (Media $media) => (bool) $media->getCustomProperty('is_primary', false))
            ?? $mediaList->first();
    }

    public function getPrimaryImageUrl(string $conversion = ''): ?string
    {
        $primary = $this->getPrimaryMedia();

        return $primary ? $primary->getUrl($conversion) : null;
    }
}
