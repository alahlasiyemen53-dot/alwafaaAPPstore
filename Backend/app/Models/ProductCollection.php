<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ProductCollection extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('collection_cover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(200)->height(200)->nonQueued();
        $this->addMediaConversion('small')->width(400)->height(400)->nonQueued();
        $this->addMediaConversion('medium')->width(800)->height(800)->nonQueued();
    }

    /** @return HasMany<ProductCollectionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProductCollectionItem::class, 'collection_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_collection_items', 'collection_id', 'product_id')
            ->withPivot(['sort_order', 'id'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function scopeCurrentlyVisible($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function getCoverUrl(?string $conversion = 'small'): ?string
    {
        $media = $this->getFirstMedia('collection_cover');
        if ($media === null) {
            return null;
        }

        if ($conversion && $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }
}
